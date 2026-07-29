import { createRouter, createWebHistory } from 'vue-router'
import routes from './routes.js'
import _get from '@/helpers/Connections/get.js';
import useUserSession from '@/composables/useAuthSession';
import { BASE_API } from '@/constants/api';
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: routes,
})

let hasCheckedAuth = false;

router.beforeEach(async (to) => {
  const isAuthRoute = to.name === 'login';
  const { user, setUser, clearUser } = useUserSession();

  // On first load or missing local user, check backend auth
  if (!hasCheckedAuth || (to.meta.requiresAuth && !user.value)) {
    try {
      const resp = await _get({ url: `${BASE_API}/auth/me`, showLoading: false });
      if (resp && resp.success && resp.data) {
        setUser(resp.data);
      } else {
        clearUser();
      }
    } catch (error) {
      clearUser();
    }
    hasCheckedAuth = true;
  }

  const isAuthenticated = !!user.value;

  // If trying to access a protected route without being authenticated
  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: "login" };
  }

  // If trying to access the login page while already authenticated
  if (isAuthRoute && isAuthenticated) {
    return { name: "home" }; // Redirect to dashboard
  }

  return true;
});

export default router
