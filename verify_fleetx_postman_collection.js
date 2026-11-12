const fs = require('fs');
const apiRoutes = fs.readFileSync('backend/routes/api.php', 'utf8').split(/\r?\n/);
const stack = [];
const routes = [];
for (let line of apiRoutes) {
  const trimmed = line.trim();
  const prefix = trimmed.match(/Route::prefix\('([^']+)'\)->group\(/);
  const middleware = trimmed.match(/Route::middleware\('([^']+)'\)->group\(/);
  if (prefix) {
    stack.push({ type: 'prefix', value: prefix[1] });
    continue;
  }
  if (middleware) {
    stack.push({ type: 'middleware', value: middleware[1] });
    continue;
  }
  if (trimmed === '});') {
    stack.pop();
    continue;
  }
  const routeMatch = trimmed.match(/Route::(get|post|put|patch|delete)\('([^']+)'/);
  if (!routeMatch) continue;
  const method = routeMatch[1].toUpperCase();
  const path = routeMatch[2];
  const prefixParts = stack.filter(s => s.type === 'prefix').map(s => s.value);
  const url = '/api/' + (prefixParts.length ? prefixParts.join('/') + '/' : '') + path;
  const normalizedUrl = url.replace(/\/\/+/, '/').replace(/\/$/, '');
  routes.push({ method, url: normalizedUrl, source: trimmed });
}
const collection = JSON.parse(fs.readFileSync('fleetx_postman_collection.json', 'utf8'));
const requests = [];
for (const folder of collection.item) {
  for (const item of folder.item) {
    const raw = item.request.url.raw.replace('{{base_url}}', '');
    const normalized = raw.replace(/https?:\/\/[^/]+/, '').replace(/\/\/+/, '/').replace(/\/$/, '');
    requests.push({ method: item.request.method, raw: normalized, name: item.name });
  }
}
function normalizePath(path) {
  return path.replace(/{{base_url}}/, '').replace(/https?:\/\/[^/]+/, '').replace(/\/\/+/, '/').replace(/\/$/, '');
}

function segments(path) {
  return normalizePath(path).split('/').filter(Boolean);
}

function pathMatches(routePath, requestPath) {
  const routeSegments = segments(routePath);
  const reqSegments = segments(requestPath);
  if (routeSegments.length !== reqSegments.length) return false;
  return routeSegments.every((seg, index) => {
    const reqSeg = reqSegments[index];
    if (seg === reqSeg) return true;
    if (seg.startsWith('{') && seg.endsWith('}') && reqSeg.startsWith('{{') && reqSeg.endsWith('}}')) return true;
    return false;
  });
}

const missing = routes.filter(r => !requests.some(req => req.method === r.method && pathMatches(r.url, req.raw)));
const extra = requests.filter(req => !routes.some(route => route.method === req.method && pathMatches(route.url, req.raw)));
console.log('routes:', routes.length);
console.log('requests:', requests.length);
console.log('missing:', missing.length);
missing.forEach(r => console.log('MISSING', r.method, r.url));
console.log('extra:', extra.length);
extra.forEach(r => console.log('EXTRA', r.method, r.raw, r.name));
