import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { App } from './app/app';
import { routes } from './app/app.routes';

beforeEach(async () => {
  await TestBed.configureTestingModule({
    imports: [App],
    providers: [
      provideRouter(routes),
      provideHttpClient(),
      provideHttpClientTesting(),
    ],
  }).compileComponents();
});

it('should create the app', () => {
  const fixture = TestBed.createComponent(App);
  expect(fixture.componentInstance).toBeTruthy();
});
