import { StatusBadge } from '../../../../shared/components/dispatcher/StatusBadge';
import { formatRequestStatusLabel, requestStatusTone } from '../utils/requestMapper';

interface RequestStatusBadgeProps {
  status: string;
}

export default function RequestStatusBadge({ status }: RequestStatusBadgeProps) {
  return <StatusBadge label={formatRequestStatusLabel(status)} tone={requestStatusTone(status)} />;
}
