/**
 * C1 helper: adapt frontend-source screens into client-app routes.
 * Run: node scripts/c1-adapt-product-screens.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const dst = path.join(__dirname, '..');
const srcDir = path.join(dst, 'src/product-source');

function adapt(content) {
  let c = content;
  c = c.split("from 'react-native-maps'").join("from '@/components/safe-map'");
  c = c.split('MapView').join('SafeMap');
  c = c.split("from '@/hooks'").join("from '@/hooks/use-theme'");
  c = c.split("from '@/components'").join("from '@/components/product'");
  c = c
    .split("require('@/assets/logos/fleetlogo.png')")
    .join("require('../../../assets/images/logos/fleetlogo.png')");
  return c;
}

const barrel = `export { GradientBackground } from '../gradient-background';
export { InputField } from '../input-field';
export { Checkbox } from '../checkbox';
export { CustomButton, GoogleLogo } from '../custom-button';
`;
fs.mkdirSync(path.join(dst, 'src/components/product'), { recursive: true });
fs.writeFileSync(path.join(dst, 'src/components/product/index.ts'), barrel);

const map = {
  'home.tsx': 'src/app/(tabs)/index.tsx',
  'shipments.tsx': 'src/app/(tabs)/shipments.tsx',
  'tracking.tsx': 'src/app/(tabs)/tracking.tsx',
  'profile.tsx': 'src/app/(tabs)/profile.tsx',
  'notifications.tsx': 'src/app/notifications.tsx',
  'shipment-details.tsx': 'src/app/shipment-details.tsx',
  'register-ui.tsx': 'src/app/register.tsx',
};

for (const [from, to] of Object.entries(map)) {
  const raw = fs.readFileSync(path.join(srcDir, from), 'utf8');
  fs.mkdirSync(path.dirname(path.join(dst, to)), { recursive: true });
  fs.writeFileSync(path.join(dst, to), adapt(raw));
  console.log('wrote', to);
}
console.log('done');
