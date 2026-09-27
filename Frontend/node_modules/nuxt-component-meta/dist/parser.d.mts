import { ComponentMeta } from 'vue-component-meta';
import { b as ExtendMetaFunction } from './shared/nuxt-component-meta.O-lHvZPc.mjs';
import '@nuxt/schema';
import './shared/nuxt-component-meta.KxfHq62s.mjs';

type ComponentMetaTransformer = (component: any, code: string) => {
    component: any;
    code: string;
};

interface Options {
    rootDir: string;
    cache?: boolean;
    cacheDir?: string;
    /**
     * Extra transformers to be run on top of component code before parsing.
     */
    transformers?: ComponentMetaTransformer[];
    /**
     * Register compiler macro functions that inject custom metadata into components.
     * @default [{ name: 'extendComponentMeta' }]
     */
    extendMetaFunctions?: ExtendMetaFunction[];
}
declare function getComponentMeta(component: string, options?: Options): ComponentMeta;

export { getComponentMeta };
export type { Options };
