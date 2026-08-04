import axios from 'axios';
import { config } from '../config.js';

/**
 * Generates a personalized subject/body for one outreach send.
 * Falls back to a lightweight "mock LLM" that varies the template with a
 * small bank of opening lines when no OPENAI_API_KEY is configured, so
 * the pipeline works end-to-end before you have a real key.
 */
export async function generatePersonalizedMessage({
  channel,
  contactName,
  company,
  templateSubject,
  templateBody,
}) {
  if (config.llmMock) {
    return mockGenerate({ channel, contactName, company, templateSubject, templateBody });
  }

  try {
    const systemPrompt =
      channel === 'sms'
        ? 'You write short, friendly, personalized SMS outreach messages (under 300 characters) for a B2B sales tool. No emojis, no hashtags.'
        : 'You write short, friendly, personalized cold outreach emails for a B2B sales tool. Keep it under 120 words, no fluff, one clear call to action.';

    const userPrompt = [
      `Recipient name: ${contactName || 'there'}`,
      `Recipient company: ${company || 'their company'}`,
      `Use this template as inspiration for tone and goal (personalize it, don't just copy it):`,
      channel === 'email' ? `Subject template: ${templateSubject || ''}` : null,
      `Body template: ${templateBody || ''}`,
      channel === 'email'
        ? 'Respond with exactly two lines: "SUBJECT: ..." then "BODY: ...".'
        : 'Respond with exactly one line: "BODY: ...".',
    ]
      .filter(Boolean)
      .join('\n');

    const res = await axios.post(
      'https://api.openai.com/v1/chat/completions',
      {
        model: config.openaiModel,
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: userPrompt },
        ],
        temperature: 0.7,
        max_tokens: 300,
      },
      {
        headers: {
          Authorization: `Bearer ${config.openaiApiKey}`,
          'Content-Type': 'application/json',
        },
        timeout: 15000,
      }
    );

    const text = res.data.choices?.[0]?.message?.content || '';
    const subjectMatch = text.match(/SUBJECT:\s*(.+)/i);
    const bodyMatch = text.match(/BODY:\s*([\s\S]+)/i);

    return {
      subject: subjectMatch ? subjectMatch[1].trim() : templateSubject,
      body: bodyMatch ? bodyMatch[1].trim() : text.trim() || templateBody,
      generated: true,
    };
  } catch (err) {
    console.error('[llm] generation failed, falling back to template merge:', err.response?.data || err.message);
    return mockGenerate({ channel, contactName, company, templateSubject, templateBody });
  }
}

const OPENING_LINES = [
  "Hope you're having a good week —",
  'Quick one for you —',
  "I'll keep this short —",
  'Reaching out because',
  'Noticed your team might find this useful —',
];

function mockGenerate({ channel, contactName, company, templateSubject, templateBody }) {
  const name = contactName || 'there';
  const co = company || 'your team';
  const opener = OPENING_LINES[Math.abs(hashCode(name + co)) % OPENING_LINES.length];

  const body = merge(templateBody, name, co).replace(/^Hi \w+,?\s*/i, `Hi ${name}, ${opener} `);
  const subject = channel === 'email' ? merge(templateSubject || 'Quick question', name, co) : undefined;

  return { subject, body, generated: false };
}

function merge(template, name, company) {
  return (template || '').replace(/\{\{name\}\}/g, name).replace(/\{\{company\}\}/g, company);
}

function hashCode(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  return hash;
}
