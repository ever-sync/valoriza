export const IS_PROD = false;

export const BASE_API = IS_PROD
  ? 'https://riodev.com.br/projetos/esc/back/public'
  : '/api';

export const BASE_HOME = IS_PROD
  ? 'https://riodev.com.br/projetos/esc/'
  : '/';