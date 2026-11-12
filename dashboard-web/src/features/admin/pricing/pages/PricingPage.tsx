import { useCallback, useEffect, useState } from 'react';
import { DollarSign } from 'lucide-react';
import { useCompanies } from '../../companies/hooks/useCompanies';
import ActivePolicyPanel from '../components/ActivePolicyPanel';
import PolicyGovernancePanel from '../components/PolicyGovernancePanel';
import PolicyTimelinePanel, { type PricingPolicyTimeline } from '../components/PolicyTimelinePanel';
import PolicyRulesReadOnly from '../components/PolicyRulesReadOnly';
import PricingEstimateResult from '../components/PricingEstimateResult';
import PricingEstimatorForm from '../components/PricingEstimatorForm';
import { pricingService, PricingApiError } from '../services/pricing.service';
import type { PricingEstimateResponse, PricingPolicy, PricingPolicySummary } from '../types/pricing.types';
import {
  buildPricingEstimatePayload,
  defaultEstimatorState,
  type PricingEstimatorFormState,
} from '../utils/buildPricingEstimatePayload';
import { flattenPricingErrors } from '../utils/formatPricing';

export default function PricingPage() {
  const { companies } = useCompanies({ page: 1, per_page: 100 });

  const [policy, setPolicy] = useState<PricingPolicy | null>(null);
  const [policies, setPolicies] = useState<PricingPolicySummary[]>([]);
  const [timeline, setTimeline] = useState<PricingPolicyTimeline | null>(null);
  const [timelineLoading, setTimelineLoading] = useState(true);
  const [timelineError, setTimelineError] = useState<string | null>(null);
  const [policyLoading, setPolicyLoading] = useState(true);
  const [policyError, setPolicyError] = useState<string | null>(null);
  const [policyStatus, setPolicyStatus] = useState<number | undefined>();
  const [showConfiguration, setShowConfiguration] = useState(true);

  const [form, setForm] = useState<PricingEstimatorFormState>(defaultEstimatorState);
  const [estimate, setEstimate] = useState<PricingEstimateResponse | null>(null);
  const [isCalculating, setIsCalculating] = useState(false);
  const [calculateError, setCalculateError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<string[]>([]);

  const loadPolicies = useCallback(async () => {
    setPolicyLoading(true);
    setPolicyError(null);
    setPolicyStatus(undefined);

    try {
      const [active, list, timelineData] = await Promise.all([
        pricingService.getActivePricingPolicy(),
        pricingService.getPricingPolicies(),
        pricingService.getPricingPolicyTimeline().catch(() => null),
      ]);
      setPolicy(active);
      setPolicies(list);
      setTimeline(timelineData);
      setTimelineError(null);
    } catch (error) {
      const apiError = error instanceof PricingApiError ? error : null;
      setPolicy(null);
      setPolicyStatus(apiError?.status);
      setPolicyError(apiError?.message || 'Unable to load the active pricing policy.');

      if (apiError?.status !== 403) {
        try {
          const [list, timelineData] = await Promise.all([
            pricingService.getPricingPolicies(),
            pricingService.getPricingPolicyTimeline().catch(() => null),
          ]);
          setPolicies(list);
          setTimeline(timelineData);
        } catch {
          setPolicies([]);
          setTimeline(null);
        }
      }
    } finally {
      setPolicyLoading(false);
      setTimelineLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadPolicies();
  }, [loadPolicies]);

  const handleFormChange = (updates: Partial<PricingEstimatorFormState>) => {
    setForm((current) => ({ ...current, ...updates }));
  };

  const handleCalculate = async () => {
    setCalculateError(null);
    setFieldErrors([]);

    try {
      const payload = buildPricingEstimatePayload(form);
      setIsCalculating(true);
      const result = await pricingService.calculatePricing(payload);
      setEstimate(result);
    } catch (error) {
      setEstimate(null);
      if (error instanceof PricingApiError) {
        setCalculateError(error.message);
        setFieldErrors(flattenPricingErrors(error.errors));
      } else {
        setCalculateError(error instanceof Error ? error.message : 'Unable to calculate pricing estimate.');
      }
    } finally {
      setIsCalculating(false);
    }
  };

  return (
    <div className="flex w-full flex-col gap-6 font-sans text-left select-none">
      <div className="flex flex-col gap-1.5">
        <div className="flex items-center gap-2">
          <DollarSign className="h-5 w-5 text-cyan-400" />
          <h1 className="text-2xl font-bold tracking-tight text-white">Pricing</h1>
        </div>
        <p className="text-xs font-normal text-slate-500">
          Commercial pricing governance and estimation. Policy configuration is backend-authoritative. Estimates are calculated by the backend.
        </p>
      </div>

      <PolicyTimelinePanel
        timeline={timeline}
        isLoading={timelineLoading}
        error={timelineError}
      />

      <PolicyGovernancePanel policies={policies} onPoliciesChanged={loadPolicies} />

      <ActivePolicyPanel
        policy={policy}
        isLoading={policyLoading}
        error={policyError}
        status={policyStatus}
        showConfiguration={showConfiguration}
        onToggleConfiguration={() => setShowConfiguration((current) => !current)}
        onRetry={() => void loadPolicies()}
      />

      {policy && showConfiguration && (
        <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
          <h2 className="mb-4 text-lg font-semibold text-white">Active Policy Configuration</h2>
          <p className="mb-4 text-xs text-slate-500">Persisted rules for the currently resolved active policy.</p>
          <PolicyRulesReadOnly policy={policy} />
        </div>
      )}

      <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-6">
        <h2 className="text-lg font-semibold text-white">Pricing Estimate</h2>
        <p className="mt-1 mb-4 text-xs text-slate-500">
          Stateless simulation. Calculate Estimate does not create shipments, invoices, or pricing snapshots.
        </p>
        <PricingEstimatorForm
          form={form}
          companies={companies}
          policies={policies}
          activePolicy={policy}
          isSubmitting={isCalculating}
          error={calculateError}
          fieldErrors={fieldErrors}
          onChange={handleFormChange}
          onSubmit={() => void handleCalculate()}
        />
        {estimate && (
          <div className="mt-6">
            <PricingEstimateResult estimate={estimate} />
          </div>
        )}
      </div>
    </div>
  );
}
