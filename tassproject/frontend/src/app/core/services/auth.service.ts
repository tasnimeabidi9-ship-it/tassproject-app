import { Injectable, computed, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { LoginResponse, User } from '../models/user.model';

const TOKEN_KEY = 'tassproject_token';
const USER_KEY = 'tassproject_user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private tokenSignal = signal<string | null>(sessionStorage.getItem(TOKEN_KEY));
  private userSignal = signal<User | null>(this.readStoredUser());

  readonly token = computed(() => this.tokenSignal());
  readonly currentUser = computed(() => this.userSignal());
  readonly isAuthenticated = computed(() => !!this.tokenSignal());
  readonly role = computed(() => this.userSignal()?.role ?? null);

  constructor(private http: HttpClient, private router: Router) {}

  private readStoredUser(): User | null {
    const raw = sessionStorage.getItem(USER_KEY);
    return raw ? (JSON.parse(raw) as User) : null;
  }

  login(email: string, password: string) {
    return this.http
      .post<LoginResponse>(`${environment.apiUrl}/auth/login`, { email, password })
      .pipe(
        tap((res) => {
          this.tokenSignal.set(res.token);
          this.userSignal.set(res.user);
          sessionStorage.setItem(TOKEN_KEY, res.token);
          sessionStorage.setItem(USER_KEY, JSON.stringify(res.user));
        })
      );
  }

  logout() {
    const token = this.tokenSignal();
    this.clearSession();
    this.router.navigate(['/login']);

    if (token) {
      this.http.post(`${environment.apiUrl}/auth/logout`, {}).subscribe({ error: () => {} });
    }
  }

  private clearSession() {
    this.tokenSignal.set(null);
    this.userSignal.set(null);
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
  }

  forceLogout() {
    this.clearSession();
    this.router.navigate(['/login']);
  }
}
