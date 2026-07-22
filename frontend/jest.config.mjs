import nextJest from "next/jest.js";

// next/jest wires up SWC transforms, tsconfig path aliases, CSS mocking, and
// the Next runtime so tests run against the same setup as the app.
const createJestConfig = nextJest({ dir: "./" });

/** @type {import('jest').Config} */
const config = {
  testEnvironment: "jsdom",
  setupFilesAfterEnv: ["<rootDir>/jest.setup.ts"],
  moduleNameMapper: {
    "^@/(.*)$": "<rootDir>/src/$1",
  },
};

export default createJestConfig(config);
