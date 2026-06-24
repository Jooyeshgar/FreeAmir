import { defineConfig, externalizeDepsPlugin } from 'electron-vite';
import { join, resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    main: {
        resolve: {
            alias: {
                '#plugin': resolve(__dirname, 'electron-plugin/dist/index.js'),
            },
        },
        build: {
            rollupOptions: {
                plugins: [
                    {
                        name: 'watch-external',
                        buildStart() {
                            this.addWatchFile(
                                join(process.env.APP_PATH, 'app', 'Providers', 'NativeAppServiceProvider.php'),
                            );
                        },
                    },
                ],
            },
        },
        plugins: [externalizeDepsPlugin()],
    },
});
