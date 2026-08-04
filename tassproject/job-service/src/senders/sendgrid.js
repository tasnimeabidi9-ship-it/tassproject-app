import axios from 'axios';
import { config } from '../config.js';

/**
 * Sends one email. Returns { success, providerMessageId, error }.
 * Falls back to a mock send (logged, not actually delivered) when no
 * SENDGRID_API_KEY is configured, so the whole pipeline works before you
 * have a real key.
 */
export async function sendEmail({ to, subject, body }) {
  if (config.emailMock) {
    const fakeId = `mock-email-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    console.log(`[sendgrid:mock] to=${to} subject="${subject}" -> ${fakeId}`);
    return { success: true, providerMessageId: fakeId };
  }

  try {
    const res = await axios.post(
      'https://api.sendgrid.com/v3/mail/send',
      {
        personalizations: [{ to: [{ email: to }] }],
        from: { email: config.sendgridFromEmail, name: config.sendgridFromName },
        subject,
        content: [{ type: 'text/plain', value: body }],
      },
      {
        headers: {
          Authorization: `Bearer ${config.sendgridApiKey}`,
          'Content-Type': 'application/json',
        },
        timeout: 10000,
      }
    );
    const providerMessageId = res.headers['x-message-id'] || null;
    return { success: true, providerMessageId };
  } catch (err) {
    return { success: false, error: err.response?.data || err.message };
  }
}
