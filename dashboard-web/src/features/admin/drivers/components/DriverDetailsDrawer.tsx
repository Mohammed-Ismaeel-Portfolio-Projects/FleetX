import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { User } from 'lucide-react';
import type { Driver } from '../types/driver.types';
import { axiosClientWithInterceptors } from '../../../../core/api';
import CurrentAssignmentPerformanceBlock from '../../../dispatcher/drivers/components/CurrentAssignmentPerformanceBlock';
import {
  formatScoreLabel,
  formatTrend,
  isPerformanceUnavailable,
} from '../../../dispatcher/drivers/utils/performanceDisplay';

interface DriverDetailsDrawerProps {
  driver: Driver | null;
  isOpen: boolean;
  isLoading: boolean;
  onClose: () => void;
  statusNameById: Record<number, string>;
}

interface PerformanceSummary {
  overall_score: number | null;
  score_label: string | null;
  confidence: string | null;
  maturity: string | null;
  trend: string | null;
  trend_delta: number | null;
  evaluated_assignments_count: number;
  incomplete_assignments_count: number;
  late_evaluations_count: number;
  last_evaluated_at: string | null;
  category_scores: Record<string, { score?: number | null; sample_count?: number }> | null;
  score_available: boolean;
}

interface RecentEvaluationRow {
  evaluation_id: number;
  assignment_id: number;
  overall_score: number | null;
  score_label: string | null;
  status: string | null;
  finalized_at: string | null;
  assignment_code: string | null;
}

function valueOf(value: string | number | boolean | null | undefined) {
  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  return value || 'Not provided';
}

function DetailSection({ title, rows }: { title: string; rows: Array<[string, string | number | boolean | null | undefined]> }) {
  return (
    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">{title}</h3>
      <dl className="mt-4 grid grid-cols-1 gap-3">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-start justify-between gap-4">
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="max-w-[62%] text-right text-xs font-semibold text-slate-200 capitalize">{valueOf(value)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function DriverDetailsDrawer({ driver, isOpen, isLoading, onClose, statusNameById }: DriverDetailsDrawerProps) {
  const closeRef = useRef<HTMLButtonElement>(null);
  const [performance, setPerformance] = useState<PerformanceSummary | null>(null);
  const [performanceError, setPerformanceError] = useState<string | null>(null);
  const [performanceLoading, setPerformanceLoading] = useState(false);
  const [recentEvaluations, setRecentEvaluations] = useState<RecentEvaluationRow[]>([]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    closeRef.current?.focus();
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  useEffect(() => {
    if (!isOpen || !driver) {
      setPerformance(null);
      setPerformanceError(null);
      setRecentEvaluations([]);
      setPerformanceLoading(false);
      return;
    }

    let cancelled = false;
    setPerformanceLoading(true);

    void (async () => {
      try {
        const perfResponse = await axiosClientWithInterceptors.get<{ data: PerformanceSummary }>(
          `/drivers/${driver.id}/performance`
        );
        if (cancelled) return;
        setPerformance(perfResponse.data.data);
        setPerformanceError(null);
      } catch {
        if (cancelled) return;
        setPerformance(null);
        setPerformanceError('Performance unavailable');
      } finally {
        if (!cancelled) setPerformanceLoading(false);
      }

      try {
        const historyResponse = await axiosClientWithInterceptors.get<{
          data: { data: RecentEvaluationRow[]; meta: unknown };
        }>(`/drivers/${driver.id}/performance/evaluations`, { params: { page: 1, per_page: 5 } });
        if (cancelled) return;
        setRecentEvaluations(historyResponse.data.data?.data ?? []);
      } catch {
        if (cancelled) return;
        setRecentEvaluations([]);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isOpen, driver]);

  const activeAssignmentId =
    driver?.active_assignment?.id != null ? String(driver.active_assignment.id) : null;

  const overallUnavailable =
    !performance || isPerformanceUnavailable(performance.maturity, performance.overall_score);

  return (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Driver details">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="absolute inset-0 bg-slate-950/70 backdrop-blur-sm"
            onClick={onClose}
          />
          <motion.aside
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ duration: 0.24 }}
            className="absolute right-0 top-0 flex h-full w-full max-w-xl flex-col border-l border-white/10 bg-[#0E1320] shadow-2xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-white/5 px-6 py-5">
              <div className="flex items-start gap-3">
                <span className="rounded-xl border border-[#0250C5]/20 bg-[#0250C5]/10 p-2 text-[#00E5FF]">
                  <User className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-lg font-bold text-white">Driver Details</h2>
                  <p className="mt-1 text-xs text-slate-500">{driver?.full_name ?? 'Loading driver information'}</p>
                </div>
              </div>
              <button
                ref={closeRef}
                type="button"
                onClick={onClose}
                className="rounded-xl p-2 text-slate-500 transition-colors hover:bg-white/5 hover:text-white"
                aria-label="Close driver details"
              >
                ×
              </button>
            </div>
            <div className="flex-1 overflow-y-auto p-6">
              {isLoading ? (
                <div className="flex h-64 items-center justify-center">
                  <span className="h-8 w-8 rounded-full border-2 border-[#0250C5] border-t-transparent animate-spin" />
                </div>
              ) : driver ? (
                <div className="flex flex-col gap-4">
                  <DetailSection
                    title="Overall Driver Performance"
                    rows={
                      performanceError
                        ? [['Status', performanceError]]
                        : performanceLoading
                          ? [['Status', 'Loading performance…']]
                          : overallUnavailable
                            ? [['Status', 'No evaluation data available']]
                            : [
                                ['Overall Score', performance!.overall_score],
                                ['Label', formatScoreLabel(performance!.score_label)],
                                ['Confidence', performance!.confidence],
                                ['Maturity', performance!.maturity],
                                ['Trend', formatTrend(performance!.trend, performance!.trend_delta)],
                                ['Evaluated Assignments', performance!.evaluated_assignments_count],
                                ['Incomplete Evaluations', performance!.incomplete_assignments_count],
                                ['Late Evaluations', performance!.late_evaluations_count],
                                ['Last Evaluated', performance!.last_evaluated_at],
                                ...Object.entries(performance!.category_scores || {}).map(([key, value]) => [
                                  key.replace(/_/g, ' '),
                                  value.score != null ? `${value.score} (n=${value.sample_count ?? 0})` : 'Not evaluable',
                                ] as [string, string]),
                              ]
                    }
                  />

                  {/* DRIVER-EVAL-08D — current trip evaluation from active_assignment identity */}
                  <CurrentAssignmentPerformanceBlock assignmentId={activeAssignmentId} />

                  {recentEvaluations.length > 0 ? (
                    <section className="rounded-2xl border border-white/5 bg-[#090D16] p-4">
                      <h3 className="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                        Recent Evaluations
                      </h3>
                      <ul className="mt-3 space-y-2">
                        {recentEvaluations.map((row) => (
                          <li key={row.evaluation_id} className="text-xs text-slate-300">
                            {row.assignment_code || `#${row.assignment_id}`}
                            {': '}
                            {row.overall_score != null
                              ? `${row.overall_score}${row.score_label ? ` · ${formatScoreLabel(row.score_label)}` : ''}`
                              : row.status === 'incomplete'
                                ? 'Insufficient evidence'
                                : 'Score unavailable'}
                            {row.finalized_at ? ` · ${row.finalized_at}` : ''}
                          </li>
                        ))}
                      </ul>
                    </section>
                  ) : null}

                  {driver.active_assignment ? (
                    <DetailSection
                      title="Active Assignment"
                      rows={[
                        ['Code', driver.active_assignment.assignment_code],
                        ['Active', driver.active_assignment.is_active],
                        ['Vehicle', driver.active_assignment.vehicle?.operational_code],
                        ['Shipment', driver.active_assignment.shipment?.shipment_number],
                      ]}
                    />
                  ) : null}

                  <DetailSection
                    title="Personal"
                    rows={[
                      ['Employee Number', driver.employee_number],
                      ['First Name', driver.first_name],
                      ['Last Name', driver.last_name],
                      ['Email', driver.email],
                      ['Phone', driver.phone],
                      ['Nationality', driver.nationality],
                    ]}
                  />
                  <DetailSection
                    title="Employment"
                    rows={[
                      ['Date of Birth', driver.date_of_birth],
                      ['Employment Date', driver.employment_date],
                      ['Status', statusNameById[driver.status_id] || `Status #${driver.status_id}`],
                      ['Active', driver.is_active],
                      ['Years of Experience', driver.years_of_experience],
                      ['License Number', driver.license_number],
                      ['License Class', driver.license_class],
                    ]}
                  />
                  <DetailSection
                    title="Certifications"
                    rows={[
                      ['License Expiry', driver.license_expiry_date],
                      ['Medical Expiry', driver.medical_certificate_expiry],
                    ]}
                  />
                  <DetailSection
                    title="Emergency Contact"
                    rows={[
                      ['Contact Name', driver.emergency_contact_name],
                      ['Contact Phone', driver.emergency_contact_phone],
                    ]}
                  />
                  <DetailSection
                    title="Additional"
                    rows={[
                      ['Notes', driver.notes],
                      ['Created At', new Date(driver.created_at).toLocaleDateString()],
                      ['Updated At', new Date(driver.updated_at).toLocaleDateString()],
                    ]}
                  />
                </div>
              ) : (
                <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5 text-sm text-red-200">
                  Driver details could not be loaded.
                </div>
              )}
            </div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
