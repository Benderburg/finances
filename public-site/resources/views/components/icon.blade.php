<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon)
@case('wallet')<path d="M20 8V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v11H5a3 3 0 0 1-3-3V6"/><path d="M20 12h-5v4h5"/>@break
@case('arrows')<path d="M3 7h18l-4-4M21 17H3l4 4"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>@break
@case('savings')<path d="M5 9a7 7 0 0 1 12-3l3-1v5l2 2v4l-3 1-1 4h-3l-1-3H9l-1 3H5l-1-5-2-3 3-4z"/><path d="M10 8h4"/><path d="M17 10h.01"/>@break
@case('target')<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>@break
@case('currency')<circle cx="12" cy="12" r="9"/><path d="M15 7c-6-2-7 8-1 10l2-1M7 10h7M7 13h7"/>@break
@case('debt')<path d="M3 12h4l3 3h6a2 2 0 0 1 0 4H9l-6-4M16 3v8M12 7h8"/>@break
@default<path d="M3 3v18h18M7 15l5-6 4 3 5-8"/>
@endswitch</svg>
