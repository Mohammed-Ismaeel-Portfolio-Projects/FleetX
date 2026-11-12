import { MapPin, Clock, Gauge, Navigation } from 'lucide-react';
import type { VehicleDetails } from '../../types/tracking.types';
import AnimatedNumber from '../AnimatedNumber';

interface TripProgressProps {
  details: VehicleDetails;
}

export default function TripProgress({ details }: TripProgressProps) {
  const tripProgress = details.tripProgress;

  if (!tripProgress) {
    return null;
  }

  const formatTime = (isoString: string) => {
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffHrs = Math.floor(diffMs / 3600000);
    const diffMins = Math.floor((diffMs % 3600000) / 60000);
    
    if (diffHrs > 24) {
      return `${Math.floor(diffHrs / 24)}d ${diffHrs % 24}h`;
    }
    return `${diffHrs}h ${diffMins}m`;
  };

  return (
    <div className="space-y-3">
      <h3 className="text-xs font-semibold text-white uppercase tracking-wider">Trip Progress</h3>
      
      {/* Route Info */}
      <div className="flex items-center justify-between rounded-lg border border-white/5 bg-[#0A0E17] p-3">
        <div className="flex items-center gap-2">
          <MapPin className="h-4 w-4 text-cyan-400" />
          <div>
            <p className="text-[10px] text-slate-500">Origin</p>
            <p className="text-sm font-semibold text-white">{tripProgress.originCity}</p>
          </div>
        </div>
        <div className="flex-1 mx-4">
          <div className="h-2 rounded-full bg-white/10 overflow-hidden">
            <div 
              className="h-full bg-gradient-to-r from-cyan-500 to-blue-500 transition-all duration-500 ease-out"
              style={{ width: `${tripProgress.progressPercent}%` }}
            />
          </div>
          <p className="mt-1 text-center text-xs text-slate-400">
            <AnimatedNumber value={tripProgress.progressPercent} />%
          </p>
        </div>
        <div className="flex items-center gap-2">
          <div className="text-right">
            <p className="text-[10px] text-slate-500">Destination</p>
            <p className="text-sm font-semibold text-white">{tripProgress.destinationCity}</p>
          </div>
          <MapPin className="h-4 w-4 text-green-400" />
        </div>
      </div>

      {/* Progress Metrics */}
      <div className="grid grid-cols-2 gap-2">
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Navigation className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Distance Remaining</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            <AnimatedNumber value={tripProgress.distanceRemaining} /> km
          </p>
        </div>
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <MapPin className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Total Distance</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            <AnimatedNumber value={tripProgress.estimatedDistance} /> km
          </p>
        </div>
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Clock className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">ETA</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {formatTime(tripProgress.estimatedArrival)}
          </p>
        </div>
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Clock className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Elapsed</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {tripProgress.elapsedTripTime}
          </p>
        </div>
      </div>

      {/* Speed Metrics */}
      <div className="grid grid-cols-2 gap-2">
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Gauge className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Avg Speed</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            <AnimatedNumber value={tripProgress.averageSpeed} /> km/h
          </p>
        </div>
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Gauge className="h-3 w-3 text-green-400" />
            <p className="text-[10px] text-slate-500">Current Speed</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            <AnimatedNumber value={tripProgress.currentSpeed} /> km/h
          </p>
        </div>
      </div>
    </div>
  );
}
