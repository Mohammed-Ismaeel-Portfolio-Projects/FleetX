import React from 'react';

export interface SignaturePoint {
  x: number;
  y: number;
}

export interface SignatureStroke {
  points: SignaturePoint[];
}

export interface SignatureRendererProps {
  strokes?: SignatureStroke[] | null;
  width?: number;
  height?: number;
}

export function SignatureRenderer({ strokes, width = 300, height = 150 }: SignatureRendererProps) {
  if (!strokes || strokes.length === 0) {
    return (
      <div className="flex h-28 w-full items-center justify-center rounded-xl border border-dashed border-white/10 bg-slate-900/50 text-xs text-slate-500">
        No signature points recorded
      </div>
    );
  }

  const paths = strokes.map((stroke, index) => {
    if (!stroke.points || stroke.points.length === 0) return null;
    const d = stroke.points.reduce((acc, point, idx) => {
      return idx === 0 ? `M ${point.x} ${point.y}` : `${acc} L ${point.x} ${point.y}`;
    }, '');
    return (
      <path
        key={index}
        d={d}
        stroke="#3B82F6"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
      />
    );
  });

  return (
    <div className="rounded-xl border border-white/10 bg-[#060911] p-3 shadow-inner">
      <svg viewBox={`0 0 ${width} ${height}`} className="h-32 w-full">
        {paths}
      </svg>
    </div>
  );
}
