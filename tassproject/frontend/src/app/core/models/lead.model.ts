export interface TargetProfile {
  id: number;
  name: string;
  industry: string | null;
  job_title: string | null;
  location: string | null;
  keywords: string[] | null;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface ScrapeJob {
  id: number;
  profile_id: number;
  profile?: { id: number; name: string };
  queries_run: number;
  leads_found: number;
  emails_enriched: number;
  status: 'pending' | 'running' | 'completed' | 'failed';
  started_at: string | null;
  finished_at: string | null;
  created_at: string;
}

export interface LeadQueueItem {
  id: number;
  raw_name: string | null;
  domain: string | null;
  email: string | null;
  confidence_score: number | null;
  match_score: number | null;
  source_url: string | null;
  approval_status: 'pending_review' | 'approved' | 'rejected';
  approved_by: number | null;
  found_at: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PlacePreviewResult {
  name: string | null;
  address: string | null;
  website: string | null;
  phone: string | null;
  rating: number | null;
  rating_count: number | null;
  category: string | null;
}
