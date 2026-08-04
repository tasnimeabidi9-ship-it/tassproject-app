import axios from 'axios';
import { config } from '../config.js';

/**
 * Sends one SMS. Returns { success, providerMessageId, error }.
 * Falls back to a mock send when Twilio credentials aren't configured.
 */
export async function sendSms({ to, body }) {
  if (config.smsMock) {
    const fakeId = `mock-sms-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    console.log(`[twilio:mock] to=${to} body="${body.slice(0, 40)}..." -> ${fakeId}`);
    return { success: true, providerMessageId: fakeId };
  }

  try {
    const url = `https://api.twilio.com/2010-04-01/Accounts/${config.twilioSid}/Messages.json`;
    const params = new URLSearchParams({
      To: to,
      From: config.twilioFromNumber,
      Body: body,
    });

    const res = await axios.post(url, params, {
      auth: { username: config.twilioSid, password: config.twilioAuthToken },
      timeout: 10000,
    });

    return { success: true, providerMessageId: res.data.sid };
  } catch (err) {
    return { success: false, error: err.response?.data || err.message };
  }
}
