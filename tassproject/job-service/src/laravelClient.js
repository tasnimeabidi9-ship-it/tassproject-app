import axios from 'axios';
import { config } from './config.js';

const client = axios.create({
  baseURL: config.laravelApiUrl,
  timeout: 10000,
  headers: { 'X-Internal-Secret': config.internalSecret },
});

export async function reportMessageResult(messageId, { status, providerMessageId, error, finalSubject, finalBody }) {
  try {
    await client.patch(`/internal/messages/${messageId}`, {
      status,
      provider_message_id: providerMessageId || null,
      error: error ? String(error).slice(0, 500) : null,
      final_subject: finalSubject ?? null,
      final_body: finalBody ?? null,
    });
  } catch (err) {
    console.error(`Failed to report status for message ${messageId}:`, err.response?.data || err.message);
  }
}
