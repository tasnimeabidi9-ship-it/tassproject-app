import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SequenceService } from '../../core/services/sequence.service';
import { Sequence, SequenceStep } from '../../core/models/sequence.model';

@Component({
  selector: 'app-sequence-builder',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './sequence-builder.html',
  styleUrl: './sequence-builder.css',
})
export class SequenceBuilder implements OnInit {
  sequences = signal<Sequence[]>([]);
  loading = signal(false);
  saving = signal(false);

  showForm = signal(false);
  name = '';
  steps: SequenceStep[] = [];

  constructor(private sequenceApi: SequenceService) {}

  ngOnInit() {
    this.load();
  }

  load() {
    this.loading.set(true);
    this.sequenceApi.listSequences().subscribe({
      next: (s) => {
        this.sequences.set(s);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  openNewForm() {
    this.name = '';
    this.steps = [{ channel: 'email', delay_days: 0, subject: '', body: '' }];
    this.showForm.set(true);
  }

  addStep() {
    const lastDelay = this.steps.length ? this.steps[this.steps.length - 1].delay_days : 0;
    this.steps.push({ channel: 'email', delay_days: lastDelay + 3, subject: '', body: '' });
  }

  removeStep(index: number) {
    this.steps.splice(index, 1);
  }

  save() {
    if (!this.name.trim() || this.steps.length === 0) return;

    this.saving.set(true);
    this.sequenceApi.createSequence({ name: this.name, steps: this.steps }).subscribe({
      next: () => {
        this.saving.set(false);
        this.showForm.set(false);
        this.load();
      },
      error: () => this.saving.set(false),
    });
  }

  delete(id: number) {
    if (!confirm('Delete this sequence? Campaigns using it will be affected.')) return;
    this.sequenceApi.deleteSequence(id).subscribe(() => this.load());
  }
}
