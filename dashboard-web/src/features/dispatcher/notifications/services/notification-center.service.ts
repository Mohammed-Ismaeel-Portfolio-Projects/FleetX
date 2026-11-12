// ============================================================================
// FleetX Notification Center Service
// Business logic and data provision for Notification Command Center
// ============================================================================

/**
 * Live Status Interface
 */
export interface LiveStatus {
  isLive: boolean;
  latency: number;
  lastUpdate: string;
}

/**
 * Notification Health Interface
 */
export interface NotificationHealth {
  status: 'Healthy' | 'Warning' | 'Critical' | 'Offline';
  systemAvailability: number;
  averageResponseTime: number;
}

/**
 * Header Summary Interface
 */
export interface HeaderSummary {
  activeAlerts: number;
  criticalAlerts: number;
  resolvedToday: number;
  averageResponseTime: number;
  systemAvailability: number;
}

/**
 * Last Sync Interface
 */
export interface LastSync {
  timestamp: string;
  status: 'success' | 'pending' | 'failed';
}

/**
 * Notification KPI Interface
 */
export interface NotificationKPI {
  id: string;
  title: string;
  primaryValue: string | number;
  secondaryLabel: string;
  trend: 'up' | 'down' | 'stable';
  trendValue: number;
  color: 'red' | 'orange' | 'blue' | 'green' | 'purple';
  sparkline: number[];
}

/**
 * Filter Statistics Interface
 */
export interface FilterStatistics {
  totalAlerts: number;
  filteredAlerts: number;
  activeFilters: number;
}

/**
 * Available Filters Interface
 */
export interface AvailableFilters {
  severity: string[];
  category: string[];
  status: string[];
  source: string[];
  region: string[];
}

/**
 * Critical Incident Interface
 */
export interface CriticalIncident {
  exists: boolean;
  id?: string;
  priority?: 'Critical' | 'High' | 'Medium' | 'Low';
  vehicle?: string;
  driver?: string;
  shipment?: string;
  customer?: string;
  origin?: string;
  destination?: string;
  currentLocation?: string;
  currentStatus?: string;
  estimatedDelay?: string;
  affectedDeliveries?: number;
  estimatedFinancialImpact?: string;
  aiConfidence?: string;
  timeSinceTriggered?: string;
  assignedOperator?: string;
  aiSummary?: string;
  systemAvailability?: string;
  activeAlerts?: number;
  lastCriticalIncident?: string;
}

/**
 * Incident Details Interface
 */
export interface IncidentDetails {
  id: string;
  severity: 'Critical' | 'High' | 'Medium' | 'Low';
  priority: 'Critical' | 'High' | 'Medium' | 'Low';
  status: 'Created' | 'Detected' | 'Acknowledged' | 'Assigned' | 'In Progress' | 'Resolved' | 'Escalated';
  createdTime: string;
  assignedOperator: string;
  description: string;
  rootCause: string;
  affectedSystem: string;
  currentSituation: string;
  vehicle: string;
  driver: string;
  shipment: string;
  customer: string;
  route: string;
  currentLocation: string;
  aiConfidence: string;
  aiPrediction: string;
  aiRootCauseAnalysis: string;
  aiExpectedOutcome: string;
  aiSuggestedResolution: string;
  estimatedDelay: string;
  financialImpact: string;
  affectedDeliveries: number;
  riskLevel: 'Low' | 'Medium' | 'High' | 'Critical';
  customerImpact: string;
  notes: IncidentNote[];
  attachments: IncidentAttachment[];
  auditTrail: AuditEntry[];
  timeline: TimelineEntry[];
}

export interface IncidentNote {
  id: string;
  author: string;
  timestamp: string;
  content: string;
}

export interface IncidentAttachment {
  id: string;
  name: string;
  type: 'photo' | 'gps' | 'diagnostic' | 'log';
  url: string;
  timestamp: string;
}

export interface AuditEntry {
  id: string;
  actor: string;
  timestamp: string;
  action: string;
}

export interface TimelineEntry {
  id: string;
  status: string;
  timestamp: string;
  actor?: string;
  description: string;
}

/**
 * Incident Lifecycle Interface
 */
export interface IncidentLifecycle {
  currentStep: 'Detected' | 'AI Verified' | 'Acknowledged' | 'Assigned' | 'Investigating' | 'Mitigation' | 'Resolved' | 'Closed';
  steps: LifecycleStep[];
}

export interface LifecycleStep {
  id: string;
  name: string;
  status: 'completed' | 'current' | 'pending';
  timestamp?: string;
  actor?: string;
}

/**
 * Incident SLA Interface
 */
export interface IncidentSLA {
  responseSLA: string;
  resolutionSLA: string;
  responseElapsed: string;
  resolutionElapsed: string;
  responseRemaining: string;
  resolutionRemaining: string;
  responseStatus: 'On Track' | 'Warning' | 'Breached';
  resolutionStatus: 'On Track' | 'Warning' | 'Breached';
}

/**
 * Incident Assignment Interface
 */
export interface IncidentAssignment {
  assignedDispatcher: string;
  assignedManager: string;
  assignedTeam: string;
  currentOwner: string;
  assignmentTime: string;
  transferHistory: TransferHistory[];
}

export interface TransferHistory {
  id: string;
  from: string;
  to: string;
  timestamp: string;
  reason: string;
}

/**
 * Incident Priority Matrix Interface
 */
export interface IncidentPriorityMatrix {
  impact: 'Low' | 'Medium' | 'High' | 'Critical';
  likelihood: 'Low' | 'Medium' | 'High' | 'Critical';
  businessCriticality: 'Low' | 'Medium' | 'High' | 'Critical';
  calculatedPriority: 'Low' | 'Medium' | 'High' | 'Critical';
}

/**
 * Incident Escalation Status Interface
 */
export interface IncidentEscalationStatus {
  currentLevel: 'Dispatcher' | 'Operations Manager' | 'Regional Manager' | 'Executive';
  escalationPath: EscalationLevel[];
}

export interface EscalationLevel {
  id: string;
  level: string;
  status: 'active' | 'escalated' | 'pending';
  timestamp?: string;
  actor?: string;
}

/**
 * Incident Resolution Summary Interface
 */
export interface IncidentResolutionSummary {
  isResolved: boolean;
  rootCause: string;
  actionsTaken: string[];
  timeToResolve: string;
  downtime: string;
  businessImpact: string;
  lessonsLearned: string;
}

/**
 * Notification Center Service
 * Encapsulates all business logic for Notification Command Center
 */
export class NotificationCenterService {
  /**
   * Get Live Status
   * Returns real-time streaming status
   */
  getLiveStatus(): LiveStatus {
    return {
      isLive: true,
      latency: 180,
      lastUpdate: '12 sec ago',
    };
  }

  /**
   * Get Notification Health
   * Returns overall system health status
   */
  getNotificationHealth(): NotificationHealth {
    return {
      status: 'Healthy',
      systemAvailability: 99.8,
      averageResponseTime: 45,
    };
  }

  /**
   * Get Header Summary
   * Returns key metrics for header display
   */
  getHeaderSummary(): HeaderSummary {
    return {
      activeAlerts: 128,
      criticalAlerts: 6,
      resolvedToday: 83,
      averageResponseTime: 45,
      systemAvailability: 99.8,
    };
  }

  /**
   * Get Last Sync
   * Returns last synchronization information
   */
  getLastSync(): LastSync {
    return {
      timestamp: '12 sec ago',
      status: 'success',
    };
  }

  /**
   * Get Executive KPIs
   * Returns executive KPI cards for Admin Dashboard
   */
  getExecutiveKPIs(): NotificationKPI[] {
    return [
      {
        id: 'critical-alerts',
        title: 'Critical Alerts',
        primaryValue: 6,
        secondaryLabel: 'Requires immediate attention',
        trend: 'down',
        trendValue: 12,
        color: 'red',
        sparkline: [8, 10, 12, 9, 7, 6, 8, 10, 11, 6],
      },
      {
        id: 'open-incidents',
        title: 'Open Incidents',
        primaryValue: 128,
        secondaryLabel: 'Active across all domains',
        trend: 'stable',
        trendValue: 2,
        color: 'blue',
        sparkline: [120, 125, 122, 128, 126, 130, 128, 127, 129, 128],
      },
      {
        id: 'resolved-today',
        title: 'Resolved Today',
        primaryValue: 83,
        secondaryLabel: 'Successfully closed',
        trend: 'up',
        trendValue: 22,
        color: 'green',
        sparkline: [60, 65, 70, 72, 75, 78, 80, 82, 83, 83],
      },
      {
        id: 'avg-response-time',
        title: 'Average Response Time',
        primaryValue: '45m',
        secondaryLabel: 'Average time to respond',
        trend: 'down',
        trendValue: 18,
        color: 'green',
        sparkline: [55, 52, 50, 48, 47, 46, 45, 44, 45, 45],
      },
      {
        id: 'ai-prediction-accuracy',
        title: 'AI Prediction Accuracy',
        primaryValue: '94.2%',
        secondaryLabel: 'Model performance',
        trend: 'up',
        trendValue: 3,
        color: 'purple',
        sparkline: [91, 92, 92, 93, 93, 94, 94, 94, 94, 94],
      },
      {
        id: 'system-availability',
        title: 'System Availability',
        primaryValue: '99.8%',
        secondaryLabel: 'System uptime',
        trend: 'up',
        trendValue: 0.2,
        color: 'green',
        sparkline: [99.5, 99.6, 99.6, 99.7, 99.7, 99.8, 99.8, 99.8, 99.8, 99.8],
      },
      {
        id: 'avg-resolution-time',
        title: 'Average Resolution Time',
        primaryValue: '45m',
        secondaryLabel: 'Average time to resolve',
        trend: 'down',
        trendValue: 18,
        color: 'green',
        sparkline: [55, 52, 50, 48, 47, 46, 45, 44, 45, 45],
      },
      {
        id: 'false-positive-rate',
        title: 'False Positive Rate',
        primaryValue: '2.8%',
        secondaryLabel: 'Alert accuracy',
        trend: 'down',
        trendValue: 15,
        color: 'green',
        sparkline: [4, 3.8, 3.5, 3.2, 3.0, 2.9, 2.8, 2.8, 2.8, 2.8],
      },
    ];
  }

  /**
   * Get Notification KPIs
   * Returns executive KPI cards for notification overview
   */
  getNotificationKPIs(): NotificationKPI[] {
    return [
      {
        id: 'critical-alerts',
        title: 'Critical Alerts',
        primaryValue: 6,
        secondaryLabel: 'Requires immediate attention',
        trend: 'down',
        trendValue: 12,
        color: 'red',
        sparkline: [8, 10, 12, 9, 7, 6, 8, 10, 11, 6],
      },
      {
        id: 'high-priority',
        title: 'High Priority Alerts',
        primaryValue: 24,
        secondaryLabel: 'Action required within 1 hour',
        trend: 'up',
        trendValue: 8,
        color: 'orange',
        sparkline: [18, 20, 22, 19, 21, 23, 24, 22, 25, 24],
      },
      {
        id: 'open-incidents',
        title: 'Open Incidents',
        primaryValue: 128,
        secondaryLabel: 'Active across all domains',
        trend: 'stable',
        trendValue: 2,
        color: 'blue',
        sparkline: [120, 125, 122, 128, 126, 130, 128, 127, 129, 128],
      },
      {
        id: 'assigned-incidents',
        title: 'Assigned Incidents',
        primaryValue: 89,
        secondaryLabel: 'Currently in progress',
        trend: 'up',
        trendValue: 15,
        color: 'purple',
        sparkline: [70, 75, 80, 82, 85, 87, 89, 88, 90, 89],
      },
      {
        id: 'resolved-today',
        title: 'Resolved Today',
        primaryValue: 83,
        secondaryLabel: 'Successfully closed',
        trend: 'up',
        trendValue: 22,
        color: 'green',
        sparkline: [60, 65, 70, 72, 75, 78, 80, 82, 83, 83],
      },
      {
        id: 'avg-resolution-time',
        title: 'Avg Resolution Time',
        primaryValue: '45m',
        secondaryLabel: 'Average time to resolve',
        trend: 'down',
        trendValue: 18,
        color: 'green',
        sparkline: [55, 52, 50, 48, 47, 46, 45, 44, 45, 45],
      },
      {
        id: 'ai-prediction-accuracy',
        title: 'AI Prediction Accuracy',
        primaryValue: '94.2%',
        secondaryLabel: 'Model performance',
        trend: 'up',
        trendValue: 3,
        color: 'purple',
        sparkline: [91, 92, 92, 93, 93, 94, 94, 94, 94, 94],
      },
      {
        id: 'false-positive-rate',
        title: 'False Positive Rate',
        primaryValue: '2.8%',
        secondaryLabel: 'Alert accuracy',
        trend: 'down',
        trendValue: 15,
        color: 'green',
        sparkline: [4, 3.8, 3.5, 3.2, 3.0, 2.9, 2.8, 2.8, 2.8, 2.8],
      },
    ];
  }

  /**
   * Get Filter Statistics
   * Returns statistics for current filter state
   */
  getFilterStatistics(): FilterStatistics {
    return {
      totalAlerts: 146,
      filteredAlerts: 38,
      activeFilters: 3,
    };
  }

  /**
   * Get Available Filters
   * Returns available filter options
   */
  getAvailableFilters(): AvailableFilters {
    return {
      severity: ['All', 'Critical', 'High', 'Medium', 'Low', 'Info'],
      category: ['Fleet', 'Shipment', 'Driver', 'Route', 'Security', 'Financial', 'Maintenance', 'AI', 'Customer', 'System'],
      status: ['Active', 'Acknowledged', 'Assigned', 'Resolved', 'Dismissed'],
      source: ['AI', 'Vehicle', 'GPS', 'System', 'Customer', 'Dispatcher', 'Security'],
      region: ['North America', 'Europe', 'Asia Pacific', 'Latin America', 'Middle East', 'Africa'],
    };
  }

  /**
   * Get Critical Incident
   * Returns the most important active incident or healthy status
   */
  getCriticalIncident(): CriticalIncident {
    // CASE 1: Critical incident exists
    return {
      exists: true,
      id: 'INC-2024-0717-001',
      priority: 'Critical',
      vehicle: 'FX-218',
      driver: 'John Martinez',
      shipment: 'SHP-78234',
      customer: 'Global Logistics Corp',
      origin: 'Los Angeles, CA',
      destination: 'Seattle, WA',
      currentLocation: 'Portland, OR (I-5 North)',
      currentStatus: 'Engine Fault - Vehicle Stopped',
      estimatedDelay: '2h 15m',
      affectedDeliveries: 3,
      estimatedFinancialImpact: '$12,500',
      aiConfidence: '94%',
      timeSinceTriggered: '18 min',
      assignedOperator: 'Sarah Chen',
      aiSummary: 'Vehicle FX-218 is experiencing a severe engine fault while transporting a refrigerated shipment. Predicted arrival delay: 2h 15m. Business impact: High. Recommended immediate intervention.',
    };

    // CASE 2: No critical incident (uncomment to use)
    // return {
    //   exists: false,
    //   systemAvailability: '99.98%',
    //   activeAlerts: 12,
    //   lastCriticalIncident: 'Yesterday 15:22',
    // };
  }

  /**
   * Get Incident Details
   * Returns detailed information for a specific incident
   */
  getIncidentDetails(alertId: string): IncidentDetails {
    return {
      id: alertId,
      severity: 'Critical',
      priority: 'Critical',
      status: 'In Progress',
      createdTime: '2024-07-17 12:15:00',
      assignedOperator: 'Sarah Chen',
      description: 'Vehicle FX-218 experienced a severe engine fault while transporting refrigerated shipment SHP-78234. Vehicle has stopped on I-5 North near Portland, OR.',
      rootCause: 'Engine overheating due to coolant system failure. Secondary issue: transmission sensor malfunction.',
      affectedSystem: 'Fleet Management System, GPS Tracking, Engine Telemetry',
      currentSituation: 'Vehicle is stationary. Driver is safe. Refrigeration unit is operational on backup power. Traffic diversion has been initiated.',
      vehicle: 'FX-218',
      driver: 'John Martinez',
      shipment: 'SHP-78234',
      customer: 'Global Logistics Corp',
      route: 'Los Angeles, CA → Seattle, WA (I-5 North)',
      currentLocation: 'Portland, OR (I-5 North, Mile 285)',
      aiConfidence: '94%',
      aiPrediction: 'Engine repair will require 2-3 hours. Shipment delay estimated at 2h 15m. 85% probability of on-time delivery after reroute.',
      aiRootCauseAnalysis: 'Coolant system failure detected at 11:45. Temperature spike pattern indicates gradual degradation over 48 hours. Maintenance records show last service 45 days ago.',
      aiExpectedOutcome: 'With immediate maintenance intervention, vehicle will be operational within 3 hours. Alternative route recommended to minimize delay impact.',
      aiSuggestedResolution: 'Deploy mobile maintenance unit to current location. Reroute shipment via backup vehicle FX-412 from Portland depot.',
      estimatedDelay: '2h 15m',
      financialImpact: '$12,500',
      affectedDeliveries: 3,
      riskLevel: 'High',
      customerImpact: 'Medium - Customer has been notified. SLA breach unlikely with current mitigation plan.',
      notes: [
        {
          id: 'NOTE-001',
          author: 'Sarah Chen',
          timestamp: '2024-07-17 12:20:00',
          content: 'Driver confirmed safe. Refrigeration unit stable. Mobile maintenance unit dispatched.',
        },
        {
          id: 'NOTE-002',
          author: 'John Martinez',
          timestamp: '2024-07-17 12:18:00',
          content: 'Engine warning light came on at 11:45. Vehicle stopped safely. No traffic issues.',
        },
      ],
      attachments: [
        {
          id: 'ATT-001',
          name: 'Engine_Diagnostic_Report.pdf',
          type: 'diagnostic',
          url: '#',
          timestamp: '2024-07-17 12:16:00',
        },
        {
          id: 'ATT-002',
          name: 'GPS_Location_Snapshot.png',
          type: 'gps',
          url: '#',
          timestamp: '2024-07-17 12:15:00',
        },
        {
          id: 'ATT-003',
          name: 'Vehicle_Photo.jpg',
          type: 'photo',
          url: '#',
          timestamp: '2024-07-17 12:17:00',
        },
        {
          id: 'ATT-004',
          name: 'System_Log_2024-07-17.log',
          type: 'log',
          url: '#',
          timestamp: '2024-07-17 12:15:00',
        },
      ],
      auditTrail: [
        {
          id: 'AUDIT-001',
          actor: 'System',
          timestamp: '2024-07-17 12:15:00',
          action: 'Incident created automatically via AI detection',
        },
        {
          id: 'AUDIT-002',
          actor: 'Sarah Chen',
          timestamp: '2024-07-17 12:16:00',
          action: 'Incident acknowledged',
        },
        {
          id: 'AUDIT-003',
          actor: 'Sarah Chen',
          timestamp: '2024-07-17 12:17:00',
          action: 'Assigned to self',
        },
        {
          id: 'AUDIT-004',
          actor: 'Sarah Chen',
          timestamp: '2024-07-17 12:18:00',
          action: 'Notified driver John Martinez',
        },
        {
          id: 'AUDIT-005',
          actor: 'Sarah Chen',
          timestamp: '2024-07-17 12:19:00',
          action: 'Dispatched mobile maintenance unit',
        },
        {
          id: 'AUDIT-006',
          actor: 'Sarah Chen',
          timestamp: '2024-07-17 12:20:00',
          action: 'Notified customer Global Logistics Corp',
        },
      ],
      timeline: [
        {
          id: 'TL-001',
          status: 'Created',
          timestamp: '2024-07-17 12:15:00',
          actor: 'System',
          description: 'AI detection triggered incident creation',
        },
        {
          id: 'TL-002',
          status: 'Detected',
          timestamp: '2024-07-17 12:15:00',
          actor: 'System',
          description: 'Engine fault detected via telemetry',
        },
        {
          id: 'TL-003',
          status: 'Acknowledged',
          timestamp: '2024-07-17 12:16:00',
          actor: 'Sarah Chen',
          description: 'Incident acknowledged by operator',
        },
        {
          id: 'TL-004',
          status: 'Assigned',
          timestamp: '2024-07-17 12:17:00',
          actor: 'Sarah Chen',
          description: 'Assigned to Sarah Chen',
        },
        {
          id: 'TL-005',
          status: 'In Progress',
          timestamp: '2024-07-17 12:18:00',
          actor: 'Sarah Chen',
          description: 'Mitigation actions initiated',
        },
      ],
    };
  }

  /**
   * Get Incident Lifecycle
   * Returns lifecycle status and steps
   */
  getIncidentLifecycle(): IncidentLifecycle {
    return {
      currentStep: 'Mitigation',
      steps: [
        {
          id: 'step-1',
          name: 'Detected',
          status: 'completed',
          timestamp: '2024-07-17 12:15:00',
          actor: 'System',
        },
        {
          id: 'step-2',
          name: 'AI Verified',
          status: 'completed',
          timestamp: '2024-07-17 12:15:30',
          actor: 'AI System',
        },
        {
          id: 'step-3',
          name: 'Acknowledged',
          status: 'completed',
          timestamp: '2024-07-17 12:16:00',
          actor: 'Sarah Chen',
        },
        {
          id: 'step-4',
          name: 'Assigned',
          status: 'completed',
          timestamp: '2024-07-17 12:17:00',
          actor: 'Sarah Chen',
        },
        {
          id: 'step-5',
          name: 'Investigating',
          status: 'completed',
          timestamp: '2024-07-17 12:18:00',
          actor: 'Sarah Chen',
        },
        {
          id: 'step-6',
          name: 'Mitigation',
          status: 'current',
          timestamp: '2024-07-17 12:20:00',
          actor: 'Sarah Chen',
        },
        {
          id: 'step-7',
          name: 'Resolved',
          status: 'pending',
        },
        {
          id: 'step-8',
          name: 'Closed',
          status: 'pending',
        },
      ],
    };
  }

  /**
   * Get Incident SLA
   * Returns SLA metrics and status
   */
  getIncidentSLA(): IncidentSLA {
    return {
      responseSLA: '15 minutes',
      resolutionSLA: '4 hours',
      responseElapsed: '5 minutes',
      resolutionElapsed: '5 minutes',
      responseRemaining: '10 minutes',
      resolutionRemaining: '3h 55m',
      responseStatus: 'On Track',
      resolutionStatus: 'On Track',
    };
  }

  /**
   * Get Incident Assignment
   * Returns assignment details and transfer history
   */
  getIncidentAssignment(): IncidentAssignment {
    return {
      assignedDispatcher: 'Sarah Chen',
      assignedManager: 'Michael Torres',
      assignedTeam: 'West Coast Operations',
      currentOwner: 'Sarah Chen',
      assignmentTime: '2024-07-17 12:17:00',
      transferHistory: [
        {
          id: 'transfer-1',
          from: 'System',
          to: 'Sarah Chen',
          timestamp: '2024-07-17 12:17:00',
          reason: 'Auto-assignment based on incident type and availability',
        },
      ],
    };
  }

  /**
   * Get Incident Priority Matrix
   * Returns priority calculation based on impact, likelihood, and business criticality
   */
  getIncidentPriorityMatrix(): IncidentPriorityMatrix {
    return {
      impact: 'High',
      likelihood: 'Medium',
      businessCriticality: 'High',
      calculatedPriority: 'Critical',
    };
  }

  /**
   * Get Incident Escalation Status
   * Returns current escalation level and path
   */
  getIncidentEscalationStatus(): IncidentEscalationStatus {
    return {
      currentLevel: 'Dispatcher',
      escalationPath: [
        {
          id: 'level-1',
          level: 'Dispatcher',
          status: 'active',
          timestamp: '2024-07-17 12:17:00',
          actor: 'Sarah Chen',
        },
        {
          id: 'level-2',
          level: 'Operations Manager',
          status: 'pending',
        },
        {
          id: 'level-3',
          level: 'Regional Manager',
          status: 'pending',
        },
        {
          id: 'level-4',
          level: 'Executive',
          status: 'pending',
        },
      ],
    };
  }

  /**
   * Get Incident Resolution Summary
   * Returns resolution details (only if resolved)
   */
  getIncidentResolutionSummary(): IncidentResolutionSummary {
    return {
      isResolved: false,
      rootCause: '',
      actionsTaken: [],
      timeToResolve: '',
      downtime: '',
      businessImpact: '',
      lessonsLearned: '',
    };
  }
}

// Export singleton instance
export const notificationCenterService = new NotificationCenterService();
