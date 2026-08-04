import { Worker } from 'bullmq';
import { connection } from '../queues/index.js';
import { sendSms } from '../senders/twilio.js';
import { generatePersonalizedMessage } from '../llm/client.js';
import { reportMessageResult } from '../laravelClient.js';

export function startSmsWorker() {
  const worker = new Worker(
    'sms-outreach',
    async (job) => {
      const { messageId, to, body, contactName, company, templateBody } = job.data;
      console.log(`[sms-worker] generating copy for message ${messageId}`);

      const generated = await generatePersonalizedMessage({
        channel: 'sms',
        contactName,
        company,
        templateBody: templateBody || body,
      });

      const finalBody = generated.body || body;

      console.log(`[sms-worker] sending message ${messageId} to ${to} (llm-generated=${generated.generated})`);
      const result = await sendSms({ to, body: finalBody });

      await reportMessageResult(messageId, {
        status: result.success ? 'sent' : 'failed',
        providerMessageId: result.providerMessageId,
        error: result.error,
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
    console.error(`[sms-worker] job ${job?.id} failed:`, err.message);
  });

  return worker;
}
