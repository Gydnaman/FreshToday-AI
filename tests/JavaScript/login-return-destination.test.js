import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const loginView = readFileSync(
    new URL('../../resources/views/auth/login.blade.php', import.meta.url),
    'utf8',
);
const functionSource = loginView.match(
    /\/\/ return-destination:start([\s\S]*?)\/\/ return-destination:end/,
);

assert.ok(functionSource, 'login view must expose the marked return normalizer');

const context = vm.createContext({ URL });
vm.runInContext(
    `${functionSource[1]}; this.normalizeReturnDestination = normalizeReturnDestination;`,
    context,
);
const normalize = context.normalizeReturnDestination;
const origin = 'https://fresh.test';

test('keeps only the path, query, and hash of same-origin destinations', () => {
    assert.equal(normalize('/orders?x=1#x', origin), '/orders?x=1#x');
    assert.equal(normalize('/catalog?sort=fresh#product-1', origin), '/catalog?sort=fresh#product-1');
    assert.equal(normalize('https://fresh.test/orders?page=2#latest', origin), '/orders?page=2#latest');
});

for (const destination of [
    null,
    '',
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    '//fresh.test/orders',
    '//evil.example/steal',
    '\\\\fresh.test/orders',
    'https://evil.example/steal',
    'http://evil.example/steal',
]) {
    test(`rejects unsafe return destination: ${String(destination)}`, () => {
        assert.equal(normalize(destination, origin), '/');
    });
}
