import js from '@eslint/js'
import globals from 'globals'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import vueParser from 'vue-eslint-parser'

/**
 * Pragmatisch: Recommended-Basis, Altlasten als warn.
 * CI (`lint:ci`) scheitert nur an Errors — Warnings sind sichtbar, blocken nicht.
 *
 * @type {import('eslint').Linter.Config[]}
 */
export default [
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      'coverage/**',
      'playwright-report/**',
      'test-results/**',
      'e2e/**',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.{js,mjs,cjs,ts,mts,cts,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
      },
      parser: vueParser,
      parserOptions: {
        parser: tseslint.parser,
        extraFileExtensions: ['.vue'],
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
    rules: {
      // TS prüft Undefiniertheit; no-undef ist in .ts/.vue oft falsch (DOM-Typen)
      'no-undef': 'off',
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': [
        'warn',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrorsIgnorePattern: '^_',
        },
      ],
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-empty-object-type': 'warn',
      '@typescript-eslint/ban-ts-comment': 'warn',
      '@typescript-eslint/no-unused-expressions': 'warn',
      'no-useless-assignment': 'warn',
      'no-extra-boolean-cast': 'warn',
      'no-useless-escape': 'warn',
      'prefer-const': 'warn',
      'no-empty': ['warn', { allowEmptyCatch: true }],
      'no-console': 'off',

      // Vue: Format/Style aus Recommended abschwächen (Altbestand)
      'vue/multi-word-component-names': 'off',
      'vue/no-v-html': 'warn',
      'vue/require-default-prop': 'off',
      'vue/require-prop-types': 'off',
      'vue/attributes-order': 'off',
      'vue/max-attributes-per-line': 'off',
      'vue/html-indent': 'off',
      'vue/html-self-closing': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/multiline-html-element-content-newline': 'off',
      'vue/first-attribute-linebreak': 'off',
      'vue/html-closing-bracket-spacing': 'off',
      'vue/html-closing-bracket-newline': 'off',
      'vue/v-on-event-hyphenation': 'off',
      'vue/order-in-components': 'off',
      'vue/one-component-per-file': 'warn',
      'vue/no-required-prop-with-default': 'warn',
      // Vuetify slot shorthand (`#item.xxx`) triggert oft false positives
      'vue/valid-v-slot': 'warn',
      'vue/no-mutating-props': 'warn',
      'vue/no-dupe-keys': 'warn',
      'vue/no-unused-vars': 'warn',
    },
  },
  {
    files: ['**/*.spec.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
]
