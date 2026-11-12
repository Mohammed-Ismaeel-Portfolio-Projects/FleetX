import { memo } from 'react';

interface TruckIconProps {
  color: string;
  heading: number;
  isSelected: boolean;
  isMoving: boolean;
  hasAlert: boolean;
  isOffline: boolean;
}

function TruckIconComponent({
  color,
  heading,
  isSelected,
  isMoving,
  hasAlert,
  isOffline,
}: TruckIconProps) {
  const size = isSelected ? 48 : 36;
  const strokeWidth = isSelected ? 2.5 : 2;
  const glow = isSelected ? `0 0 20px ${color}, 0 0 40px ${color}40` : 'none';
  const pulse = hasAlert ? 'pulse 2s infinite' : 'none';
  const opacity = isOffline ? 0.5 : 1;
  const animation = isMoving ? 'float 3s ease-in-out infinite' : 'none';

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 48 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      style={{
        transform: `rotate(${heading}deg)`,
        filter: glow,
        opacity,
        animation: `${pulse}, ${animation}`,
      }}
    >
      <defs>
        <style>
          {`
            @keyframes pulse {
              0%, 100% { opacity: 1; }
              50% { opacity: 0.6; }
            }
            @keyframes float {
              0%, 100% { transform: translateY(0); }
              50% { transform: translateY(-2px); }
            }
          `}
        </style>
      </defs>
      {/* Truck body */}
      <path
        d="M8 16H32V28H8V16Z"
        fill={color}
        stroke="#0E1320"
        strokeWidth={strokeWidth}
        strokeLinejoin="round"
      />
      {/* Truck cab */}
      <path
        d="M32 16H40V24H32V16Z"
        fill={color}
        stroke="#0E1320"
        strokeWidth={strokeWidth}
        strokeLinejoin="round"
      />
      {/* Truck front */}
      <path
        d="M40 18H44V22H40V18Z"
        fill={color}
        stroke="#0E1320"
        strokeWidth={strokeWidth}
        strokeLinejoin="round"
      />
      {/* Wheels */}
      <circle cx="14" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" strokeWidth={strokeWidth} />
      <circle cx="26" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" strokeWidth={strokeWidth} />
      <circle cx="38" cy="28" r="4" fill="#1a1a2e" stroke="#0E1320" strokeWidth={strokeWidth} />
      {/* Direction indicator */}
      <path
        d="M24 4L28 12H20L24 4Z"
        fill={color}
        stroke="#0E1320"
        strokeWidth={strokeWidth}
        strokeLinejoin="round"
      />
    </svg>
  );
}

export default memo(TruckIconComponent);
