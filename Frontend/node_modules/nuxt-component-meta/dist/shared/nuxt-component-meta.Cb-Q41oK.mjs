import { parseSync } from 'oxc-parser';
import { walk, isBindingIdentifier } from 'oxc-walker';
import MagicString from 'magic-string';
import { createJiti } from 'jiti';
import { findStaticImports, parseStaticImport } from 'mlly';
import { createCheckerByJson } from 'vue-component-meta';
import { existsSync, readFileSync } from 'fs';
import { joinURL } from 'ufo';

const SCRIPT_BLOCK_RE = /<script\b(?:"[^"]*"|'[^']*'|[^>])*>([\s\S]*?)<\/script>/gi;
function extractScriptContent(code, filename) {
  if (!filename.endsWith(".vue")) return code;
  const blocks = [];
  let match;
  SCRIPT_BLOCK_RE.lastIndex = 0;
  while (match = SCRIPT_BLOCK_RE.exec(code)) {
    blocks.push(match[1]);
  }
  return blocks.join("\n");
}
function collectReferencedIdentifiers(node) {
  const names = /* @__PURE__ */ new Set();
  walk(node, {
    enter(n, parent) {
      if (n.type !== "Identifier") return;
      if (isBindingIdentifier(n, parent)) return;
      if (parent?.type === "MemberExpression" && parent.property === n && !parent.computed) return;
      if (parent?.type === "Property" && parent.key === n && !parent.computed) return;
      names.add(n.name);
    }
  });
  return names;
}
function buildEvalModule(content, argNode) {
  const referencedNames = collectReferencedIdentifiers(argNode);
  const argSource = content.slice(argNode.start, argNode.end);
  if (!referencedNames.size) {
    return `export default (${argSource})`;
  }
  const neededImports = [];
  for (const rawImport of findStaticImports(content)) {
    const parsed = parseStaticImport(rawImport);
    const importedNames = [
      parsed.defaultImport,
      parsed.namespacedImport,
      ...Object.values(parsed.namedImports ?? {})
    ].filter(Boolean);
    if (importedNames.some((name) => referencedNames.has(name))) {
      neededImports.push(rawImport.code);
    }
  }
  return [...neededImports, `export default (${argSource})`].join("\n");
}
function evaluateMacroArgument(content, argNode, filename) {
  const moduleSource = buildEvalModule(content, argNode);
  try {
    const jiti = createJiti(filename, { moduleCache: false, fsCache: false });
    const result = jiti.evalModule(moduleSource, { filename });
    const value = result?.default ?? result;
    if (value === null || value === void 0) return void 0;
    if (Array.isArray(value)) return value;
    if (typeof value !== "object") return void 0;
    const proto = Object.getPrototypeOf(value);
    if (proto !== Object.prototype && proto !== null) return void 0;
    return value;
  } catch {
    return void 0;
  }
}
function extractMacroMeta(code, macros, filename = "component.vue") {
  if (!macros.length) return [];
  const macroNames = new Set(macros.map((m) => m.name));
  if (![...macroNames].some((name) => code.includes(name))) return [];
  const content = extractScriptContent(code, filename);
  if (!content.trim()) return [];
  const parseFilename = filename.endsWith(".vue") ? filename.replace(".vue", ".ts") : filename;
  let program;
  try {
    program = parseSync(parseFilename, content).program;
  } catch {
    return [];
  }
  const results = [];
  walk(program, {
    enter(node) {
      if (node.type !== "ExpressionStatement" || node.expression.type !== "CallExpression" || node.expression.callee.type !== "Identifier") return;
      const macroName = node.expression.callee.name;
      if (!macroNames.has(macroName)) return;
      const args = node.expression.arguments;
      if (!args.length) return;
      const argType = args[0].type;
      if (argType !== "ObjectExpression" && argType !== "ArrayExpression") return;
      const extracted = evaluateMacroArgument(content, args[0], parseFilename);
      if (!extracted) return;
      const macro = macros.find((m) => m.name === macroName);
      if (Array.isArray(extracted)) {
        if (!macro.transform) return;
        results.push(macro.transform(extracted));
      } else {
        results.push(macro.transform ? macro.transform(extracted) : extracted);
      }
    }
  });
  return results;
}
function stripMacroCalls(code, macroNames, filename = "component.js") {
  if (!macroNames.length) return void 0;
  if (!macroNames.some((name) => code.includes(name))) return void 0;
  let program;
  try {
    program = parseSync(filename, code).program;
  } catch {
    return void 0;
  }
  const nameSet = new Set(macroNames);
  const s = new MagicString(code);
  let changed = false;
  walk(program, {
    enter(node) {
      if (node.type !== "ExpressionStatement" || node.expression.type !== "CallExpression" || node.expression.callee.type !== "Identifier") return;
      if (!nameSet.has(node.expression.callee.name)) return;
      s.remove(node.start, node.end);
      changed = true;
    }
  });
  if (!changed) return void 0;
  return {
    code: s.toString(),
    map: s.generateMap({ hires: true })
  };
}

function createMetaChecker(opts) {
  const baseUrl = joinURL(opts.rootDir, ".nuxt");
  let paths = void 0;
  try {
    const appTsconfig = JSON.parse(readFileSync(joinURL(baseUrl, "tsconfig.app.json"), "utf8"));
    const sharedTsconfig = JSON.parse(readFileSync(joinURL(baseUrl, "tsconfig.shared.json"), "utf8"));
    paths = {
      ...appTsconfig.compilerOptions.paths,
      ...sharedTsconfig.compilerOptions.paths
    };
  } catch {
  }
  return createCheckerByJson(
    opts.rootDir,
    {
      extends: `${opts.rootDir}/tsconfig.json`,
      skipLibCheck: true,
      include: opts.include?.map((path) => {
        const ext = path.split(".").pop();
        return ["vue", "ts", "tsx", "js", "jsx"].includes(ext) ? tryResolveTypesDeclaration(path) : `${path}/**/*`;
      }),
      exclude: [],
      ...paths ? { compilerOptions: { baseUrl, paths } } : {}
    },
    opts.checkerOptions || {
      forceUseTs: true,
      schema: true
      // Enable schema expansion by default
    }
  );
}
function tryResolveTypesDeclaration(fullPath) {
  const isNodeModule = fullPath.includes("node_modules");
  let resolvedPath = fullPath;
  if (isNodeModule && fullPath.endsWith(".vue")) {
    const patterns = [
      fullPath.replace(".vue", ".d.vue.ts"),
      fullPath.replace(".vue", ".vue.d.ts"),
      fullPath.replace(".vue", ".d.ts")
    ];
    for (const pattern of patterns) {
      if (existsSync(pattern)) {
        resolvedPath = pattern;
        break;
      }
    }
  }
  return resolvedPath;
}

const slotReplacer = (_, _before, slotName, _rest) => `<slot ${_before || ""}${slotName === "default" ? "" : `name="${slotName}"`}`;
const defaultTransformers = [
  // @nuxt/content support
  (component, code) => {
    if (code.includes("MDCSlot")) {
      code = code.replace(/<MDCSlot\s*([^>]*)?:use="\$slots\.([a-zA-Z0-9_]+)"/gm, slotReplacer);
      code = code.replace(/<MDCSlot\s*([^>]*)?name="([a-zA-Z0-9_]+)"/gm, slotReplacer);
      code = code.replace(/<\/MDCSlot>/gm, "</slot>");
    }
    if (code.includes("ContentSlot")) {
      code = code.replace(/<ContentSlot\s*([^>]*)?:use="\$slots\.([a-zA-Z0-9_]+)"/gm, slotReplacer);
      code = code.replace(/<ContentSlot\s*([^>]*)?name="([a-zA-Z0-9_]+)"/gm, slotReplacer);
      code = code.replace(/<\/ContentSlot>/gm, "</slot>");
    }
    const name = code.match(/(const|let|var) ([a-zA-Z][a-zA-Z-_0-9]*) = useSlots\(\)/)?.[2] || "$slots";
    const _slots = code.match(new RegExp(`${name}\\.[a-zA-Z]+`, "gm"));
    if (_slots) {
      const slots = _slots.map((s) => s.replace(name + ".", "")).map((s) => `<slot name="${s}" />`);
      code = code.replace(/<template>/, `<template>
${slots.join("\n")}
`);
    }
    const slotNames = code.match(/(const|let|var) {([^}]+)}\s*=\s*useSlots\(\)/)?.[2];
    if (slotNames) {
      const slots = slotNames.trim().split(",").map((s) => s.trim().split(":")[0]?.trim()).map((s) => `<slot name="${s}" />`);
      code = code.replace(/<template>/, `<template>
${slots.join("\n")}
`);
    }
    if (/declare const __VLS_export/.test(code)) {
      const matchWithSlots = code.match(/__VLS_WithSlots<\s*import\("vue"\)\.DefineComponent<([\s\S]*?)>,\s*([A-Za-z0-9_]+)\s*>/m);
      const matchDefineOnly = matchWithSlots ? null : code.match(/import\("vue"\)\.DefineComponent<([\s\S]*?)>/m);
      const generic = matchWithSlots?.[1] || matchDefineOnly?.[1] || "any";
      const head = code.split(/declare const __VLS_export/)[0] || "";
      const extend = matchWithSlots ? ` & { new (): { $slots: ${matchWithSlots?.[2]} } }` : "";
      code = [
        `${head}`,
        `export default {} as (import("vue").DefineComponent<${generic}>${extend});`
      ].join("\n").replace("export default _default;", "");
    }
    return { component, code };
  }
];

export { createMetaChecker as c, defaultTransformers as d, extractMacroMeta as e, stripMacroCalls as s, tryResolveTypesDeclaration as t };
