import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { LeadQueueItem, PaginatedResponse, ScrapeJob, TargetProfile } from '../models/lead.model';

@Injectable({ providedIn: 'root' })
export class LeadService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  // Target profiles
  listProfiles(): Observable<TargetProfile[]> {
    return this.http.get<TargetProfile[]>(`${this.base}/profiles`);
  }

  createProfile(payload: Partial<TargetProfile>): Observable<TargetProfile> {
    return this.http.post<TargetProfile>(`${this.base}/profiles`, payload);
  }

  deleteProfile(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.base}/profiles/${id}`);
  }

  // Scraper
  startScrape(profileId: number): Observable<ScrapeJob> {
    return this.http.post<ScrapeJob>(`${this.base}/scraper/start`, { profile_id: profileId });
  }

  jobStatus(jobId: number): Observable<ScrapeJob> {
    return this.http.get<ScrapeJob>(`${this.base}/scraper/status/${jobId}`);
  }

  recentJobs(): Observable<ScrapeJob[]> {
    return this.http.get<ScrapeJob[]>(`${this.base}/scraper/jobs`);
  }

  // Lead approval queue
  queue(params: {
    approval_status?: string;
    min_score?: number;
    search?: string;
    page?: number;
  }): Observable<PaginatedResponse<LeadQueueItem>> {
    let httpParams = new HttpParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, String(value));
      }
    });
    return this.http.get<PaginatedResponse<LeadQueueItem>>(`${this.base}/leads/queue`, {
      params: httpParams,
    });
  }

  approve(id: number): Observable<LeadQueueItem> {
    return this.http.patch<LeadQueueItem>(`${this.base}/leads/${id}/approve`, {});
  }

  reject(id: number, reason?: string): Observable<LeadQueueItem> {
    return this.http.patch<LeadQueueItem>(`${this.base}/leads/${id}/reject`, { reason });
  }

  bulkApprove(minScore: number): Observable<{ approved_count: number }> {
    return this.http.post<{ approved_count: number }>(`${this.base}/leads/bulk-approve`, {
      min_score: minScore,
    });
  }
}
