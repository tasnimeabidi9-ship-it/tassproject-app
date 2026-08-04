export interface PowerBiEmbedInfo {
  embedToken: string;
  embedUrl: string;
  reportId: string;
}

export interface PowerBiReportConfig {
  label: string;
  id: string | null;
  configured: boolean;
  external_url: string | null;
}

export interface PowerBiConfigResponse {
  reports: Record<'campaign' | 'leadfinder' | 'pipeline', PowerBiReportConfig>;
}
