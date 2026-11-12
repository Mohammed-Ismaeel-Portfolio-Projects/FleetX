export default function LoadingState() {
  return (
    <div className="flex flex-col gap-4">
      <div className="h-8 animate-pulse rounded-lg bg-white/5" />
      <div className="h-12 animate-pulse rounded-lg bg-white/5" />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        {Array.from({ length: 6 }).map((_, index) => (
          <div key={index} className="h-24 animate-pulse rounded-xl bg-white/5" />
        ))}
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="h-96 animate-pulse rounded-xl bg-white/5" />
        <div className="col-span-2 h-96 animate-pulse rounded-xl bg-white/5" />
      </div>
    </div>
  );
}
