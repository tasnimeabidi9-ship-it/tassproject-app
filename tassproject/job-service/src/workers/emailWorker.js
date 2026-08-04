import { Worker } from 'bullmq';
import { connection } from '../queues/index.js';
import { sendEmail } from '../senders/sendgrid.js';
import { generatePersonalizedMessage } from '../llm/client.js';
import { reportMessageResult } from '../laravelClient.js';

export function startEmailWorker() {
  const worker = new Worker(
    'email-outreach',
    async (job) => {
      const { messageId, to, subject, body, contactName, company, templateSubject, templateBody } = job.data;
      console.log(`[email-worker] generating copy for message ${messageId}`);

      const generated = await generatePersonalizedMessage({
        channel: 'email',
        contactName,
        company,
        templateSubject: templateSubject || subject,
        templateBody: templateBody || body,
      });

      const finalSubject = generated.subject || subject;
      const finalBody = generated.body || body;

      console.log(`[email-worker] sending message ${messageId} to ${to} (llm-generated=${generated.generated})`);
      const result = await sendEmail({ to, subject: finalSubject, body: finalBody });

      await reportMessageResult(messageId, {
        status: result.success ? 'sent' : 'failed',
        providerMessageId: result.providerMessageId,
        error: result.error,
        finalSubject,
        finalBody,
      });

      if (!result.success) {
        throw new Error(typeof result.error === 'string' ? result.error : 'send failed');
      }

      return result;
    },
    { connection, concurrency: 5 }
  );

  worker.on('failed', (job, err) => {
    console.error(`[email-worker] job ${job?.id} failed:`, err.message);
  });

  return worker;
}
