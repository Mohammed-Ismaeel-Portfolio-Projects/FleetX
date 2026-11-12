import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useCommunication } from '../hooks/useCommunication';
import { useMessaging } from '../hooks/useMessaging';
import { useAuth } from '../../../../core/auth';
import { CommunicationHeader } from '../components/CommunicationHeader';
import { CommunicationKPIs } from '../components/CommunicationKPIs';
import { ContactsPanel } from '../components/ContactsPanel';
import { ConversationsPanel } from '../components/ConversationsPanel';
import { AttentionQueue } from '../components/ConversationList';
import { ActiveConversation } from '../components/ActiveConversation';
import { ConversationDetails } from '../components/ConversationDetails';
import { QuickActions } from '../components/QuickActions';
import { BroadcastPanel } from '../components/BroadcastPanel';
import { EmergencyPanel } from '../components/EmergencyPanel';
import { OperationalEvents } from '../components/OperationalEvents';
import {
  DispatcherSurface,
  LoadingState,
  ErrorState,
} from '../../../../shared/components/dispatcher';
import { cn } from '../../../../lib/utils';
import { dispatcherDesignTokens } from '../../../../shared/constants/dispatcherDesignSystem';
import type { Conversation } from '../types/messaging.types';

/**
 * Layout (M4):
 * Header → KPIs →
 * Left: Contacts / Quick / Emergency / Broadcast
 * Right: Conversations → Active Conversation + Ops Context → Needs Attention → Events
 */
export default function CommunicationCenterPage() {
  const { user } = useAuth();
  const [threadShipmentId, setThreadShipmentId] = useState<string | null>(null);

  const {
    viewModel,
    isLoading,
    isRefreshing,
    error,
    search,
    setSearch,
    availabilityFilter,
    setAvailabilityFilter,
    selectedDriverId,
    setSelectedDriverId,
    selectedDriver,
    filteredDrivers,
    shipmentContext,
    shipmentContextError,
    shipmentContextLoading,
    refreshData,
  } = useCommunication({ contextShipmentId: threadShipmentId });

  const messaging = useMessaging({ selectedDriver });
  const {
    releaseListPin,
    selectConversation,
    refreshConversations,
    refreshActiveThread,
    openForDriver,
    canOpenDriverConversation,
    loadOlder,
    retryThread,
    submitMessage,
    setComposerText,
  } = messaging;

  useEffect(() => {
    setThreadShipmentId(messaging.activeConversation?.shipmentId ?? null);
  }, [messaging.activeConversation?.shipmentId]);

  const workspaceRef = useRef<HTMLDivElement | null>(null);
  const [focusPulse, setFocusPulse] = useState(false);
  const focusTimerRef = useRef<number | null>(null);

  const knownDriverIds = useMemo(() => {
    if (!viewModel) return new Set<string>();
    return new Set(viewModel.drivers.map((d) => d.id));
  }, [viewModel]);

  const conversationsByShipment = useMemo(() => {
    const map = new Map<string, Conversation>();
    for (const c of messaging.conversations) {
      if (!map.has(c.shipmentId)) map.set(c.shipmentId, c);
      if (c.type === 'dispatcher_driver') map.set(c.shipmentId, c);
    }
    return map;
  }, [messaging.conversations]);

  const handleSelectDriver = useCallback(
    (id: string) => {
      releaseListPin();
      setSelectedDriverId(id);
      window.requestAnimationFrame(() => {
        const el = workspaceRef.current;
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        el.focus({ preventScroll: true });
      });
      setFocusPulse(true);
      if (focusTimerRef.current != null) {
        window.clearTimeout(focusTimerRef.current);
      }
      focusTimerRef.current = window.setTimeout(() => {
        setFocusPulse(false);
        focusTimerRef.current = null;
      }, 1200);
    },
    [releaseListPin, setSelectedDriverId]
  );

  const handleSelectConversation = useCallback(
    (conversation: Conversation) => {
      selectConversation(conversation);
      window.requestAnimationFrame(() => {
        workspaceRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        workspaceRef.current?.focus({ preventScroll: true });
      });
    },
    [selectConversation]
  );

  const handleOpenConversationFromIssue = useCallback(
    (shipmentId: string) => {
      const existing = conversationsByShipment.get(shipmentId);
      if (!existing) return;
      handleSelectConversation(existing);
    },
    [conversationsByShipment, handleSelectConversation]
  );

  const handleOpenDriverConversation = useCallback(() => {
    if (!selectedDriver) return;
    void openForDriver(selectedDriver);
  }, [openForDriver, selectedDriver]);

  const handleRefresh = useCallback(() => {
    refreshData();
    refreshConversations();
    refreshActiveThread();
  }, [refreshActiveThread, refreshConversations, refreshData]);

  useEffect(() => {
    return () => {
      if (focusTimerRef.current != null) {
        window.clearTimeout(focusTimerRef.current);
      }
    };
  }, []);

  if (isLoading && !viewModel) {
    return (
      <LoadingState
        title="Loading communication center"
        description="Loading driver contacts, conversations, and operational context."
      />
    );
  }

  if (!viewModel) {
    return (
      <div className="flex flex-col gap-4">
        <ErrorState
          title="Communication center unavailable"
          description={error ?? 'Unable to load operational communication context.'}
        />
        <button
          type="button"
          onClick={handleRefresh}
          className={cn(dispatcherDesignTokens.buttonPrimary, 'mx-auto px-4 py-2.5')}
        >
          Retry
        </button>
      </div>
    );
  }

  const criticalMetric = viewModel.metrics.find((m) => m.id === 'critical-alerts');

  return (
    <DispatcherSurface className="w-full text-left font-sans select-none">
      <div className="flex w-full flex-col gap-6">
        <CommunicationHeader
          data={{
            lastUpdated: viewModel.lastUpdated,
            driverTotal: viewModel.driverTotal,
            sectionErrors: viewModel.sectionErrors,
          }}
          isRefreshing={isRefreshing}
          onRefresh={handleRefresh}
          messagingConnected={messaging.messagingConnected}
          conversationsLoading={messaging.conversationsState === 'loading'}
          conversationCount={messaging.conversations.length}
          conversationsError={messaging.conversationsError}
          realtimeStatus={messaging.realtimeStatus}
        />

        <CommunicationKPIs
          metrics={viewModel.metrics}
          sourceErrors={{
            driverKpis: viewModel.sectionErrors.driverKpis,
            dashboard: viewModel.sectionErrors.dashboard,
          }}
        />

        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.35fr]">
          <div className="space-y-6">
            <ContactsPanel
              drivers={filteredDrivers}
              total={viewModel.driverTotal}
              search={search}
              onSearch={setSearch}
              availabilityFilter={availabilityFilter}
              onAvailabilityFilter={setAvailabilityFilter}
              selectedDriverId={selectedDriverId}
              onSelectDriver={handleSelectDriver}
              sourceError={viewModel.sectionErrors.drivers}
            />
            <QuickActions
              selectedDriver={selectedDriver}
              onOpenConversation={
                canOpenDriverConversation ? handleOpenDriverConversation : undefined
              }
              messagingAvailable={canOpenDriverConversation}
            />
            <EmergencyPanel
              criticalAlerts={criticalMetric?.value ?? 'Unavailable'}
              sourceError={viewModel.sectionErrors.dashboard}
            />
            <BroadcastPanel />
          </div>

          <div className="space-y-6">
            <ConversationsPanel
              conversations={messaging.conversations}
              state={messaging.conversationsState}
              error={messaging.conversationsError}
              selectedConversationId={messaging.selectedConversationId}
              onSelect={handleSelectConversation}
              onRetry={refreshConversations}
            />

            <div className="grid gap-6 2xl:grid-cols-[1.35fr_0.85fr]">
              <ActiveConversation
                ref={workspaceRef}
                selectedDriver={selectedDriver}
                shipment={shipmentContext}
                shipmentLoading={shipmentContextLoading}
                focusPulse={focusPulse}
                currentUserId={user?.id ?? null}
                activeConversation={messaging.activeConversation}
                messages={messaging.messages}
                threadState={messaging.threadState}
                threadError={messaging.threadError}
                gateMessage={messaging.gateMessage}
                hasOlderMessages={messaging.hasOlderMessages}
                loadingOlder={messaging.loadingOlder}
                onLoadOlder={() => void loadOlder()}
                onRetryThread={retryThread}
                composerText={messaging.composerText}
                onComposerChange={setComposerText}
                onSend={() => void submitMessage()}
                isSending={messaging.isSending}
                sendError={messaging.sendError}
                lastReadAt={messaging.lastReadAt}
                onOpenDriverConversation={
                  canOpenDriverConversation ? handleOpenDriverConversation : undefined
                }
                canOpenDriverConversation={canOpenDriverConversation}
                scrollSignal={messaging.scrollSignal}
              />
              <ConversationDetails
                driver={selectedDriver}
                shipment={shipmentContext}
                shipmentError={shipmentContextError}
                shipmentLoading={shipmentContextLoading}
              />
            </div>

            <AttentionQueue
              issues={viewModel.issues}
              sourceError={viewModel.sectionErrors.issues}
              knownDriverIds={knownDriverIds}
              onFocusDriver={handleSelectDriver}
              conversationsByShipment={conversationsByShipment}
              onOpenConversation={handleOpenConversationFromIssue}
            />

            <OperationalEvents
              items={viewModel.activity}
              sourceError={viewModel.sectionErrors.activity}
            />
          </div>
        </div>

        {error && viewModel ? (
          <div
            className={cn(
              'rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100'
            )}
          >
            Ops refresh warning: {error}. Messaging may still be usable.
          </div>
        ) : null}
      </div>
    </DispatcherSurface>
  );
}
