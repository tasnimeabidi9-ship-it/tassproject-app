import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SettingsService } from '../../core/services/settings.service';
import { SettingsData } from '../../core/models/settings.model';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './settings.html',
  styleUrl: './settings.css',
})
export class Settings implements OnInit {
  data = signal<SettingsData | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);
  saving = signal(false);
  saved = signal(false);

  form = {
    auto_approve_threshold: 80,
    auto_approve_enabled: true,
    max_requests_per_minute: 10,
  };

  constructor(private settingsApi: SettingsService) {}

  ngOnInit() {
    this.load();
  }

  load() {
    this.loading.set(true);
    this.error.set(null);
    this.settingsApi.get().subscribe({
      next: (data) => {
        this.data.set(data);
        this.form = {
          auto_approve_threshold: data.config.auto_approve_threshold,
          auto_approve_enabled: data.config.auto_approve_enabled,
          max_requests_per_minute: data.config.max_requests_per_minute,
        };
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.error.set('Could not load settings.');
      },
    });
  }

  integrationEntries(): Array<{ key: string; configured: boolean; label: string; detail: string }> {
    const integrations = this.data()?.integrations ?? {};
    return Object.entries(integrations).map(([key, value]) => ({
      key,
      configured: value.configured,
      label: value.label,
      detail: value.detail,
    }));
  }

  save() {
    this.saving.set(true);
    this.saved.set(false);
    this.settingsApi
      .update({
        config: {
          auto_approve_threshold: Number(this.form.auto_approve_threshold),
          auto_approve_enabled: this.form.auto_approve_enabled,
          max_requests_per_minute: Number(this.form.max_requests_per_minute),
        },
      })
      .subscribe({
        next: () => {
          this.saving.set(false);
          this.saved.set(true);
          setTimeout(() => this.saved.set(false), 2500);
        },
        error: () => this.saving.set(false),
      });
  }

  updateRole(user: { id: number; role: string }) {
    this.settingsApi.update({ user: { id: user.id, role: user.role } }).subscribe({
      next: () => this.load(),
    });
  }
}
