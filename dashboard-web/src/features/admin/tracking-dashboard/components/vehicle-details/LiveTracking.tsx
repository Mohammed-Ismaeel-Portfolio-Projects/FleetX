import { MapPin, Navigation, Signal, Clock, Wifi } from 'lucide-react';
import type { VehicleDetails } from '../../types/tracking.types';

interface LiveTrackingProps {
  details: VehicleDetails;
}

export default function LiveTracking({ details }: LiveTrackingProps) {
  const liveTracking = details.liveTracking;

  if (!liveTracking) {
    return null;
  }

  const getSignalColor = (quality: string) => {
    switch (quality.toLowerCase()) {
      case 'good':
        return 'text-green-400';
      case 'weak':
        return 'text-yellow-400';
      case 'lost':
        return 'text-red-400';
      default:
        return 'text-slate-400';
    }
  };

  const formatTime = (isoString: string) => {
    const date = new Date(isoString);
    return date.toLocaleTimeString();
  };

  return (
    <div className="space-y-3">
      <h3 className="text-xs font-semibold text-white uppercase tracking-wider">Live Tracking</h3>
      
      <div className="grid grid-cols-2 gap-2">
        {/* GPS Coordinates */}
        <div className="col-span-2 rounded-lg border border-white/5 bg-[#0A0E17] p-3">
          <div className="flex items-center gap-2">
            <MapPin className="h-4 w-4 text-cyan-400" />
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-500">GPS Coordinates</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {liveTracking.gpsCoordinates}
          </p>
        </div>

        {/* Heading */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Navigation className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Heading</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {liveTracking.heading.toFixed(1)}°
          </p>
        </div>

        {/* Signal Quality */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Signal className={`h-3 w-3 ${getSignalColor(liveTracking.signalQuality)}`} />
            <p className="text-[10px] text-slate-500">Signal</p>
          </div>
          <p className={`mt-1 text-sm font-semibold capitalize ${getSignalColor(liveTracking.signalQuality)}`}>
            {liveTracking.signalQuality}
          </p>
        </div>

        {/* GPS Accuracy */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <MapPin className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">GPS Accuracy</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {liveTracking.gpsAccuracy}
          </p>
        </div>

        {/* Last Update */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Clock className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Last Update</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {formatTime(liveTracking.lastUpdate)}
          </p>
        </div>

        {/* Last Communication */}
        <div className="rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Wifi className="h-3 w-3 text-cyan-400" />
            <p className="text-[10px] text-slate-500">Last Comm</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {formatTime(liveTracking.lastCommunication)}
          </p>
        </div>

        {/* Online Duration */}
        <div className="col-span-2 rounded-lg border border-white/5 bg-[#0A0E17] p-2">
          <div className="flex items-center gap-2">
            <Clock className="h-3 w-3 text-green-400" />
            <p className="text-[10px] text-slate-500">Online Duration</p>
          </div>
          <p className="mt-1 font-mono text-sm font-semibold text-white">
            {liveTracking.onlineDuration}
          </p>
        </div>
      </div>
    </div>
  );
}
