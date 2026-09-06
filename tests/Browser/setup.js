import { spawnSync } from 'node:child_process';
import { env, php } from '../../playwright.config.js';
export default function setup() {
    const result = spawnSync(php, ['tests/Browser/setup.php'], { env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
}

