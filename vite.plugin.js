import { resolve } from 'path';

export default function PanelPlugin() {
    const pluginPath = resolve(__dirname);

    return {
        name: 'panel-plugin',
        config: () => ({
            build: {
                rollupOptions: {
                    input: {
                        'panel': resolve(pluginPath, 'resources/js/app.js'),
                    },
                    output: {
                        entryFileNames: 'js/[name].js',
                        chunkFileNames: 'js/[name].js',
                        assetFileNames: (assetInfo) => {
                            if (assetInfo.name.endsWith('.css')) {
                                return 'css/[name][extname]';
                            }
                            return 'assets/[name][extname]';
                        },
                    },
                },
                outDir: resolve(pluginPath, 'dist'),
                emptyOutDir: true,
            },
        }),
    };
}
