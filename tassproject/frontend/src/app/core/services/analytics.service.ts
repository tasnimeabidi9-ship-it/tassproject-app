import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AiActionLogEntry, AnalyticsOverview, CampaignAnalytics, DashboardData } from '../models/analytics.model';

@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  dashboard(): Observable<DashboardData> {
    return this.http.get<DashboardData>(`${this.base}/analytics/dashboard`);
  }

  overview(): Observable<AnalyticsOverview> {
    return this.http.get<AnalyticsOverview>(`${this.base}/analytics/overview`);
  }

  campaignStats(campaignId: number): Observable<CampaignAnalytics> {
    return this.http.get<CampaignAnalytics>(`${this.base}/analytics/campaign/${campaignId}`);
  }

  actionsLog(filters: {
    status?: string;
    channel?: string;
    campaign_id?: number;
    page?: number;
  }): Observable<{ data: AiActionLogEntry[]; current_page: number; last_page: number; total: number }> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params = params.set(key, String(value));
      }
    });
    return this.http.get<{ data: AiActionLogEntry[]; current_page: number; last_page: number; total: number }>(
      `${this.base}/ai/actions-log`,
      { params }
    );
  }

  cancelAction(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.base}/ai/actions/${id}/cancel`);
  }
}
