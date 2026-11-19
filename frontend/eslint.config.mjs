import nextVitals from "eslint-config-next/core-web-vitals"

const config = [
  ...nextVitals,
  {
    rules: {
      // React Compiler rules (new in eslint-plugin-react-hooks 7 / Next 16). Reported as
      // warnings so existing code keeps building; fix them gradually.
      "react-hooks/set-state-in-effect": "warn",
      "react-hooks/preserve-manual-memoization": "warn",
      "react-hooks/purity": "warn",
      "react-hooks/refs": "warn",
      "react-hooks/immutability": "warn",
      "react-hooks/static-components": "warn",
      "react-hooks/use-memo": "warn",
      "no-restricted-syntax": [
        "warn",
        {
          selector: "Literal[value=/(?:^|\\s)(?:ml|mr|pl|pr)-[0-9]/]",
          message: "Use logical Tailwind classes (ms-/me-/ps-/pe-) instead of physical ml-/mr-/pl-/pr-.",
        },
        {
          selector: "Literal[value=/(?:^|\\s)text-(?:left|right)(?:\\s|$)/]",
          message: "Use text-start/text-end instead of text-left/text-right.",
        },
      ],
    },
  },
  { ignores: [".next/**", "node_modules/**", "e2e/**", "public/**", "playwright-report/**", "test-results/**"] },
]

export default config
