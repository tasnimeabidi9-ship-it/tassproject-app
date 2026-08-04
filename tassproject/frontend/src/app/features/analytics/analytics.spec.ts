import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { Analytics } from './analytics';
import { AnalyticsService } from '../../core/services/analytics.service';
import { AnalyticsOverview } from '../../core/models/analytics.model';

describe('Analytics', () => {
  let component: Analytics;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Analytics],
      providers: [
        {
          provide: AnalyticsService,
          useValue: { overview: () => of({} as AnalyticsOverview) },
        },
      ],
    }).compileComponents();

    component = TestBed.createComponent(Analytics).componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('formats best send time from hour and day', () => {
    component.overview.set({ best_send_time: { hour: 9, day: 'Monday' } } as AnalyticsOverview);
    expect(component.bestSendTime()).toEqual({
      day: 'Monday',
      hour: 9,
      label: 'Monday at 9:00 AM',
    });
  });

  it('converts 24-hour time to 12-hour labels', () => {
    component.overview.set({ best_send_time: { hour: 15, day: 'Friday' } } as AnalyticsOverview);
    expect(component.bestSendTime().label).toBe('Friday at 3:00 PM');
  });

  it('handles midnight and noon correctly', () => {
    component.overview.set({ best_send_time: { hour: 0, day: 'Sunday' } } as AnalyticsOverview);
    expect(component.bestSendTime().label).toBe('Sunday at 12:00 AM');

    component.overview.set({ best_send_time: { hour: 12, day: 'Sunday' } } as AnalyticsOverview);
    expect(component.bestSendTime().label).toBe('Sunday at 12:00 PM');
  });

  it('returns an empty message when there are no opens', () => {
    component.overview.set({ best_send_time: { hour: null, day: null } } as AnalyticsOverview);
    expect(component.bestSendTime().label).toBe('Not enough opened messages yet');
  });
});
