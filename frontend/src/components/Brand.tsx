import { useT } from "../i18n";

export function Logo({ className = "" }: { className?: string }) {
  return (
    <span
      className={`norocel-logo ${className}`}
      role="img"
      aria-label="Norocel"
    >
      <img
        className="norocel-logo__normal"
        src="/icons/logo.svg"
        width="1040"
        height="280"
        alt=""
      />
      <img
        className="norocel-logo__light"
        src="/icons/logo-light.svg"
        width="1040"
        height="280"
        alt=""
      />
    </span>
  );
}

export function LogoLoader({ fullscreen = false }: { fullscreen?: boolean }) {
  const t = useT();
  return (
    <div
      className={`logo-loader${fullscreen ? " logo-loader--fullscreen" : " loading-state"}`}
      role="status"
      aria-label={t("loading")}
    >
      <img
        className="logo-loader__animation"
        src="/icons/loader.svg"
        width="280"
        height="420"
        alt=""
      />
      <span className="logo-loader__name" aria-hidden="true">
        Norocel
      </span>
      <span className="logo-loader__label">{t("loading")}</span>
    </div>
  );
}
