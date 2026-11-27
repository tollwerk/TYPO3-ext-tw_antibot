/**
 * Configuration of the JavaScript tests
 *
 * tw-antibot has no JavaScript of its own: the protection works without it. The configuration stands ready for the
 * day it gets some, and counts every module in Resources/Public towards the coverage.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        coverage: { include: ['Resources/Public/**/*.js'] },
    },
});
