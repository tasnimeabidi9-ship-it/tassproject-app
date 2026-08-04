import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { UserRole } from '../models/user.model';

/**
 * Usage in routes:
 *   {
 *     path: 'settings',
 *     canActivate: [authGuard, roleGuard],
 *     data: { roles: ['admin'] },
 *     component: SettingsComponent,
 *   }
 */
export const roleGuard: CanActivateFn = (route) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const allowedRoles = (route.data?.['roles'] as UserRole[] | undefined) ?? [];
  const userRole = auth.role();

  if (allowedRoles.length === 0 || (userRole && allowedRoles.includes(userRole))) {
    return true;
  }

  router.navigate(['/dashboard']);
  return false;
};
