import { spawn, spawnSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const baseUrl = 'http://127.0.0.1:8011';
const requestsPerRoute = 50;
const concurrency = 10;
const routes = ['/up', '/', '/login'];
const server = spawn('php', [
    'artisan',
    'serve',
    '--env=e2e',
    '--host=127.0.0.1',
    '--port=8011',
    '--no-reload',
], {
    cwd: process.cwd(),
    env: { ...process.env, APP_ENV: 'e2e' },
    stdio: ['ignore', 'ignore', 'pipe'],
});

let serverError = '';
server.stderr.on('data', (chunk) => { serverError += chunk.toString(); });

async function waitForServer() {
    for (let attempt = 0; attempt < 40; attempt += 1) {
        try {
            const response = await fetch(`${baseUrl}/up`);
            if (response.ok) return;
        } catch {}
        await delay(250);
    }
    throw new Error(`Local server did not start. ${serverError}`);
}

function percentile(values, ratio) {
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.min(Math.ceil(sorted.length * ratio) - 1, sorted.length - 1)];
}

async function exerciseRoute(path) {
    const durations = [];
    let next = 0;
    let failures = 0;

    async function worker() {
        while (next < requestsPerRoute) {
            next += 1;
            const started = performance.now();
            try {
                const response = await fetch(`${baseUrl}${path}`, { redirect: 'manual' });
                if (response.status >= 400) failures += 1;
                await response.arrayBuffer();
            } catch {
                failures += 1;
            }
            durations.push(performance.now() - started);
        }
    }

    await Promise.all(Array.from({ length: concurrency }, worker));
    return {
        route: path,
        requests: durations.length,
        failures,
        p50_ms: Math.round(percentile(durations, 0.5)),
        p95_ms: Math.round(percentile(durations, 0.95)),
        max_ms: Math.round(Math.max(...durations)),
    };
}

try {
    await waitForServer();
    const results = [];
    for (const route of routes) results.push(await exerciseRoute(route));
    console.table(results);
    if (results.some((result) => result.failures > 0)) process.exitCode = 1;
} finally {
    if (process.platform === 'win32') {
        spawnSync('taskkill', ['/pid', String(server.pid), '/t', '/f'], { stdio: 'ignore' });
    } else {
        server.kill('SIGTERM');
    }
}
