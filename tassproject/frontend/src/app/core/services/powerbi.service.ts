import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PowerBiConfigResponse, PowerBiEmbedInfo } from '../models/powerbi.model';

export type ReportKey = 'campaign' | 'leadfinder' | 'pipeline';

@Injectable({ providedIn: 'root' })
export class PowerBiService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getEmbedToken(report: ReportKey): Observable<PowerBiEmbedInfo> {
    return this.http.get<PowerBiEmbedInfo>(`${this.base}/powerbi/embed-token`, {
      params: { report },
    });
  }

  getConfig(): Observable<PowerBiConfigResponse> {
    return this.http.get<PowerBiConfigResponse>(`${this.base}/powerbi/config`);
  }
}
