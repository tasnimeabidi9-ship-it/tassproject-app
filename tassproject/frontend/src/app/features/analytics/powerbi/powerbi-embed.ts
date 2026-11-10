import {
  Component,
  ElementRef,
  Input,
  OnChanges,
  OnDestroy,
  SimpleChanges,
  ViewChild,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import * as pbi from 'powerbi-client';
import { PowerBiService, ReportKey } from '../../../core/services/powerbi.service';

@Component({
  selector: 'app-powerbi-embed',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './powerbi-embed.html',
  styleUrl: './powerbi-embed.css',
})
export class PowerBiEmbed implements OnChanges, OnDestroy {
  @Input({ required: true }) report!: ReportKey;
  @ViewChild('container', { static: true }) container!: ElementRef<HTMLDivElement>;

  loading = signal(true);
  error = signal<string | null>(null);

  private powerbiService = new pbi.service.Service(
    pbi.factories.hpmFactory,
    pbi.factories.wpmpFactory,
    pbi.factories.routerFactory
  );
  private embeddedReport: pbi.Report | null = null;
  private refreshHandle: ReturnType<typeof setInterval> | null = null;

  constructor(private powerbi: PowerBiService) {}

  ngOnChanges(changes: SimpleChanges) {
    if (changes['report'] && this.report) {
      this.loadAndEmbed();
    }
  }

  ngOnDestroy() {
    if (this.refreshHandle) clearInterval(this.refreshHandle);
    if (this.container?.nativeElement) {
      this.powerbiService.reset(this.container.nativeElement);
    }
  }

  private loadAndEmbed() {
    this.loading.set(true);
    this.error.set(null);

    this.powerbi.getEmbedToken(this.report).subscribe({
      next: (info) => {
        const config = {
          type: 'report',
          tokenType: pbi.models.TokenType.Embed,
          accessToken: info.embedToken,
          embedUrl: info.embedUrl,
          id: info.reportId,
          permissions: pbi.models.Permissions.Read,
          settings: {
            panes: { filters: { visible: false }, pageNavigation: { visible: true } },
          },
        };

        this.embeddedReport = this.powerbiService.embed(
          this.container.nativeElement,
          config
        ) as pbi.Report;

        this.embeddedReport.on('loaded', () => this.loading.set(false));
        this.embeddedReport.on('error', () => {
          this.error.set('Unable to render this report. Please try again later.');
          this.loading.set(false);
        });

        if (this.refreshHandle) clearInterval(this.refreshHandle);
        this.refreshHandle = setInterval(() => this.refreshToken(), 50 * 60 * 1000);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(
          err?.error?.message ??
            'Unable to load this report. Please check your connection and try again.'
        );
      },
    });
  }

  private refreshToken() {
    this.powerbi.getEmbedToken(this.report).subscribe({
      next: (info) => {
        this.embeddedReport?.setAccessToken(info.embedToken).catch(() => {});
      },
    });
  }
}
