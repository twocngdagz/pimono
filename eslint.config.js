// Flat config for ESLint v9+
import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default [
  js.configs.recommended,
  // Vue flat recommended config
  ...vue.configs['flat/recommended'],
  {
    files: ['resources/js/**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 2023,
      sourceType: 'module',
    },
    rules: {
      // Allow single-word component names for brevity in small app
      'vue/multi-word-component-names': 'off',
    },
  },
  // Disable formatting-related lint rules (delegate to Prettier)
  prettier,
];

