import cors from 'cors';
import express from 'express';
import { config } from './config.js';
import { emailQueue, queueForChannel, smsQueue } from './queues/index.js';

const app = express();
app.use(cors());
app.use(express.json());

function requireInternalSecret(req, res, next) {
  const secret = req.header('X-Internal-Secret');
  if (!secret || secret !== config.internalSecret) {
    return res.status(401).json({ message: 'Invalid or missing internal secret.' });
  }
  next();
}

app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    emailMock: config.emailMock,
    smsMock: config.smsMock,
    llmMock: config.llmMock,
  });
});

/**
 * POST /schedule
 * Called by Laravel once per sequence step, per contact, when a campaign
 * starts. Laravel has already created the `messages` row (status =
 * 'scheduled') with a placeholder-merged fallback subject/body — this
 * endpoint enqueues the timed send. The worker will call the LLM (or
 * mock) right before sending to generate the real personalized copy,
 * using contact_name/company/template_* as inspiration.
 *
 * Body: {
 *   message_id: number,
 *   channel: "email" | "sms",
 *   to: string,
 *   subject?: string,        // fallback (already placeholder-merged)
 *   body: string,             // fallback (already placeholder-merged)
 *   contact_name?: string,
 *   company?: string,
 *   template_subject?: string,  // raw, with {{name}}/{{company}} intact
 *   template_body?: string,     // raw, with {{name}}/{{company}} intact
 *   delay_ms: number
 * }
 */
app.post('/schedule', requireInternalSecret, async (req, res) => {
  const {
    message_id,
    channel,
    to,
    subject,
    body,
    contact_name,
    company,
    template_subject,
    template_body,
    delay_ms,
  } = req.body || {};

  if (!message_id || !channel || !to || !body) {
    return res.status(422).json({ message: 'message_id, channel, to, and body are required.' });
  }

  const queue = queueForChannel(channel);
  const job = await queue.add(
    `send-${channel}-${message_id}`,
    {
      messageId: message_id,
      to,
      subject,
      body,
      contactName: contact_name,
      company,
      templateSubject: template_subject,
      templateBody: template_body,
    },
    {
      delay: Math.max(0, delay_ms || 0),
      jobId: `message-${message_id}`,
      attempts: 3,
      backoff: { type: 'exponential', delay: 60000 },
      removeOnComplete: 500,
      removeOnFail: 500,
    }
  );

  res.status(201).json({ job_id: job.id, queue: channel });
});

/**
 * DELETE /schedule/:messageId
 * Cancels a scheduled (not-yet-fired) job.
 */
app.delete('/schedule/:messageId', requireInternalSecret, async (req, res) => {
  const jobId = `message-${req.params.messageId}`;

  for (const queue of [emailQueue, smsQueue]) {
    const job = await queue.getJob(jobId);
    if (job) {
      const state = await job.getState();
      if (state === 'completed' || state === 'active') {
        return res.status(409).json({ message: `Job already ${state}, cannot cancel.` });
      }
      await job.remove();
      return res.json({ message: 'Cancelled.' });
    }
  }

  res.status(404).json({ message: 'No scheduled job found for this message.' });
});

app.listen(config.port, () => {
  console.log(`Job service API listening on http://127.0.0.1:${config.port}`);
  console.log(`emailMock=${config.emailMock}, smsMock=${config.smsMock}, llmMock=${config.llmMock}`);
});
