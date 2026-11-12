import { motion } from 'framer-motion';
import type { LucideIcon } from 'lucide-react';

interface FeatureCardProps {
  icon: LucideIcon;
  title: string;
  description: string;
  index: number;
}

export default function FeatureCard({ icon: Icon, title, description, index }: FeatureCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 30 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-100px" }}
      transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1], delay: index * 0.08 }}
      whileHover={{ y: -8, boxShadow: "0 20px 40px -15px rgba(15, 23, 42, 0.08)", borderColor: "rgba(2, 80, 197, 0.15)" }}
      className="bg-white border border-slate-100 rounded-3xl p-8 flex flex-col items-center text-center transition-all duration-300 shadow-sm shadow-slate-100/40 relative group cursor-pointer"
    >
      {/* Icon Wrapper matching the reference (rounded blue/slate box) */}
      <div className="w-14 h-14 rounded-2xl bg-blue-50/60 flex items-center justify-center border border-blue-100/50 mb-6 transition-all duration-300 group-hover:bg-[#0250C5]/10 group-hover:border-[#0250C5]/20 select-none">
        <Icon className="w-6 h-6 text-[#0250C5] transition-transform duration-300 group-hover:scale-105" />
      </div>

      {/* Title */}
      <h3 className="text-xl font-bold text-slate-900 mb-3 tracking-tight font-sans">
        {title}
      </h3>

      {/* Description */}
      <p className="text-slate-500 text-sm font-normal leading-relaxed font-sans max-w-[240px]">
        {description}
      </p>
    </motion.div>
  );
}
