import { motion } from 'framer-motion';
import { User, Headset, Truck, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

interface RoleItem {
  badge: string;
  badgeStyle: string;
  icon: LucideIcon;
  title: string;
  description: string;
  responsibilities: string[];
  colorClass: string;
}

const rolesData: RoleItem[] = [
  {
    badge: 'Admin',
    badgeStyle: 'bg-red-50/70 text-red-600 border-red-200/50',
    icon: ShieldCheck,
    title: 'System Administrator',
    description: 'Full system control and enterprise oversight.',
    responsibilities: [
      'Manage users and permissions',
      'Configure fleet and system settings',
      'Monitor operations and analytics',
      'Financial reporting and billing'
    ],
    colorClass: 'bg-red-500',
  },
  {
    badge: 'Dispatcher',
    badgeStyle: 'bg-cyan-50/70 text-cyan-600 border-cyan-200/50',
    icon: Headset,
    title: 'Fleet Dispatcher',
    description: 'Core operational role managing daily logistics.',
    responsibilities: [
      'Assign drivers and trucks to shipments',
      'Monitor real-time shipment progress',
      'Handle route changes and exceptions',
      'Coordinate with clients and drivers'
    ],
    colorClass: 'bg-[#00E5FF]',
  },
  {
    badge: 'Driver',
    badgeStyle: 'bg-amber-50/70 text-amber-600 border-amber-200/50',
    icon: Truck,
    title: 'Truck Driver',
    description: 'Execute shipments and provide real-time tracking.',
    responsibilities: [
      'Follow AI-optimized routes',
      'Provide GPS location updates',
      'Capture proof of delivery',
      'Report issues and delays'
    ],
    colorClass: 'bg-amber-500',
  },
  {
    badge: 'Client',
    badgeStyle: 'bg-blue-50/70 text-blue-600 border-blue-200/50',
    icon: User,
    title: 'Shipper Client',
    description: 'Create requests and track shipments.',
    responsibilities: [
      'Submit shipment requests',
      'Track cargo in real-time',
      'Receive status notifications',
      'View delivery confirmations'
    ],
    colorClass: 'bg-[#0250C5]',
  },
];

export default function RoleSuite() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
      },
    },
  };

  const cardVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <section id="role-suite" className="py-24 md:py-28 bg-white w-full overflow-hidden border-t border-slate-100 relative">
      {/* Subtle ambient lighting */}
      <div className="absolute top-0 left-10 w-[300px] h-[300px] bg-slate-50/50 rounded-full blur-[80px] pointer-events-none" />

      <div className="max-w-7xl mx-auto px-6 md:px-16 w-full relative z-10 flex flex-col items-center">
        
        {/* Header Title */}
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 font-sans tracking-tight mb-3">
            Built for Every Role
          </h2>
          <p className="text-slate-500 text-[15px] font-normal leading-relaxed max-w-2xl mx-auto">
            Unified platform connecting administrators, dispatchers, drivers, and clients.
          </p>
        </div>

        {/* 4-column Roles Grid */}
        <motion.div 
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 w-full"
          variants={containerVariants}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: "-50px" }}
        >
          {rolesData && rolesData.length > 0 ? (
            rolesData.map((role) => (
              <motion.div
                key={role.title}
                variants={cardVariants}
                whileHover={{ y: -6, boxShadow: "0 20px 40px -15px rgba(15, 23, 42, 0.06)" }}
                className="bg-white border border-slate-200 rounded-[20px] p-6.5 flex flex-col items-start text-left transition-all duration-300 shadow-sm shadow-slate-100/50 cursor-pointer relative overflow-hidden"
              >
                {/* Top Pill Badge */}
                <span className={`px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border ${role.badgeStyle} mb-5 select-none font-sans`}>
                  {role.badge}
                </span>

                {/* Icon */}
                <role.icon className="w-6 h-6 text-slate-800 mb-4 flex-shrink-0" />

                {/* Title */}
                <h3 className="font-bold text-slate-800 text-[16px] mb-2 tracking-tight font-sans">
                  {role.title}
                </h3>

                {/* Description */}
                <p className="text-slate-400 text-[12px] font-normal leading-relaxed font-sans mb-4">
                  {role.description}
                </p>

                {/* Responsibilities */}
                <ul className="space-y-2 flex-grow">
                  {role.responsibilities && role.responsibilities.length > 0 ? (
                    role.responsibilities.map((resp, idx) => (
                      <li key={idx} className="flex items-start gap-2 text-slate-500 text-[11px] font-normal leading-snug font-sans">
                        <div className="w-1 h-1 rounded-full bg-slate-300 mt-1.5 flex-shrink-0" />
                        <span>{resp}</span>
                      </li>
                    ))
                  ) : null}
                </ul>

                {/* Bottom colored accent border matching UI reference */}
                <div className={`h-[4px] absolute bottom-0 left-0 right-0 ${role.colorClass} rounded-b-[20px]`} />
              </motion.div>
            ))
          ) : (
            <div className="col-span-full text-center py-12 text-slate-500">
              <p>Loading roles...</p>
            </div>
          )}
        </motion.div>

      </div>
    </section>
  );
}
