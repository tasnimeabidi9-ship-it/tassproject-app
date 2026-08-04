import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { IntegrationStatus, SettingsConfig, SettingsData } from '../models/settings.model';

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  get(): Observable<SettingsData> {
    return this.http.get<SettingsData>(`${this.base}/settings`);
  }

  update(payload: {
    config?: Partial<SettingsConfig>;
    user?: { id: number; role: string };
  }): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.base}/settings`, payload);
  }

  integrations(): Observable<Record<string, IntegrationStatus>> {
    return this.http.get<Record<string, IntegrationStatus>>(`${this.base}/settings/integrations`);
  }
}
