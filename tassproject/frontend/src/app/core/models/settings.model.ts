export interface IntegrationStatus {
  configured: boolean;
  label: string;
  detail: string;
}

export interface SettingsConfig {
  auto_approve_threshold: number;
  auto_approve_enabled: boolean;
  max_requests_per_minute: number;
}

export interface SettingsData {
  integrations: Record<string, IntegrationStatus>;
  config: SettingsConfig;
  users: Array<{ id: number; name: string; email: string; role: 'admin' | 'rep' | 'readonly'; last_login: string | null }>;
}
