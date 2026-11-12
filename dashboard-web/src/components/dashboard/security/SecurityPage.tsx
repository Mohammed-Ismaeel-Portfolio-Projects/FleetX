import { motion } from 'framer-motion';
import { Shield, Truck, UserCheck, Briefcase } from 'lucide-react';
import SecurityHeader from './SecurityHeader';
import RoleSummaryCard from './RoleSummaryCard';
import AccessMatrix from './AccessMatrix';
import SecurityAlertsWidget from './SecurityAlertsWidget';
import ThreatDetectionAi from './ThreatDetectionAi';
import PolicyEngine from './PolicyEngine';
import ActiveSessions from './ActiveSessions';
import AuditLog from './AuditLog';

export default function SecurityPage() {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.08,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 15 },
    visible: {
      opacity: 1,
      y: 0,
      transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
    },
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="flex flex-col gap-6 w-full text-left"
    >
      {/* 1. Page Header */}
      <motion.div variants={itemVariants} className="w-full">
        <SecurityHeader />
      </motion.div>

      {/* 2. Role Management Overview (4 Summary Cards Row) */}
      <motion.div 
        variants={itemVariants} 
        className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 w-full"
      >
        <RoleSummaryCard
          role="Admin"
          badge="Master"
          badgeStyle="bg-cyan-500/10 text-cyan-400 border-cyan-500/20"
          description="Full system access & critical overrides authority."
          usersCount="12"
          icon={Shield}
        />
        <RoleSummaryCard
          role="Dispatcher"
          badge="Ops"
          badgeStyle="bg-blue-500/10 text-blue-400 border-blue-500/20"
          description="Fleet orchestration, scheduling & route configurations."
          usersCount="64"
          icon={Briefcase}
        />
        <RoleSummaryCard
          role="Driver"
          badge="Field"
          badgeStyle="bg-slate-700/20 text-slate-400 border-slate-700/30"
          description="Mobile telemetry updates & driver logbook accesses."
          usersCount="1,402"
          icon={Truck}
        />
        <RoleSummaryCard
          role="Client"
          badge="Ext"
          badgeStyle="bg-amber-500/10 text-amber-400 border-amber-500/20"
          description="Read-only tracking portal access & dispatch logs."
          usersCount="210"
          icon={UserCheck}
        />
      </motion.div>

      {/* 3. Row 2: Access Control Matrix & Security Alerts incident feed */}
      <motion.div 
        variants={itemVariants} 
        className="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full items-stretch"
      >
        {/* Left: Matrix checklist (col-span-8) */}
        <div className="lg:col-span-8 h-full">
          <AccessMatrix />
        </div>
        {/* Right: Security Incident feed (col-span-4) */}
        <div className="lg:col-span-4 h-full">
          <SecurityAlertsWidget />
        </div>
      </motion.div>

      {/* 4. Row 3: Threat Detection AI & Policy configurations */}
      <motion.div 
        variants={itemVariants} 
        className="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full items-stretch"
      >
        {/* Left: Threat Analysis AI widget (col-span-8) */}
        <div className="lg:col-span-8 h-full">
          <ThreatDetectionAi />
        </div>
        {/* Right: Policy timeouts list (col-span-4) */}
        <div className="lg:col-span-4 h-full">
          <PolicyEngine />
        </div>
      </motion.div>

      {/* 5. Row 4: Active Connected Sessions Cards Grid */}
      <motion.div variants={itemVariants} className="w-full">
        <ActiveSessions />
      </motion.div>

      {/* 6. Row 5: Immutable Audit Logs Timeline list */}
      <motion.div variants={itemVariants} className="w-full">
        <AuditLog />
      </motion.div>

    </motion.div>
  );
}
