import { Satellite, Route, Zap, Clock } from 'lucide-react';
import FeatureCard from './FeatureCard';

const featuresData = [
  {
    icon: Satellite,
    title: 'Real-time Tracking',
    description: 'Sub-second precision GPS positioning across your entire global operations grid.',
  },
  {
    icon: Route,
    title: 'AI Route Optimization',
    description: 'Intelligent engines recalculate millions of variables to find the fastest path instantly.',
  },
  {
    icon: Zap,
    title: 'Smart Dispatching',
    description: 'Automated assignment matching drivers to orders based on proximity and vehicle capacity.',
  },
  {
    icon: Clock,
    title: 'Predictive ETA',
    description: 'Machine learning models that account for traffic, weather, and historical logistics bottlenecks.',
  },
];

export default function Features() {
  return (
    <section id="features" className="py-24 md:py-28 bg-white relative w-full overflow-hidden">
      {/* Decorative ambient elements */}
      <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-blue-50/20 rounded-full blur-[100px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-cyan-50/20 rounded-full blur-[100px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10 flex flex-col items-center text-center">
        
        {/* Header Stack matching the UI reference */}
        <div className="mb-16 md:mb-20 flex flex-col items-center">
          <span className="text-[#0250C5] text-[11px] font-extrabold uppercase tracking-[0.22em] mb-3.5 select-none font-sans">
            Innovation in Motion
          </span>
          <h2 className="text-3xl md:text-[38px] font-extrabold text-slate-900 font-sans tracking-tight leading-tight max-w-2xl">
            The Next Generation of Logistics Control
          </h2>
          {/* Blue underline accent line */}
          <div className="w-16 h-[3.5px] bg-[#0250C5] mt-5.5 rounded-full" />
        </div>

        {/* Responsive Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8 w-full">
          {featuresData.map((feat, idx) => (
            <FeatureCard
              key={feat.title}
              icon={feat.icon}
              title={feat.title}
              description={feat.description}
              index={idx}
            />
          ))}
        </div>

      </div>
    </section>
  );
}
