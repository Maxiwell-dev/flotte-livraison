import type { ModuleHooks } from './module.mjs'

declare module '@nuxt/schema' {
  interface NuxtHooks extends ModuleHooks {}
}

export { type ComponentData, type ComponentMetaParserOptions, type ExtendHookData, type ExtendMetaFunction, type HookData, type ModuleHooks, type NuxtComponentMeta, type TransformersHookData } from './shared/nuxt-component-meta.O-lHvZPc.mjs'

export { type JsonSchema } from './shared/nuxt-component-meta.KxfHq62s.mjs'

export { type ModuleOptions, default } from './module.mjs'
