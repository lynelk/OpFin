import Image from "next/image";

/** OpFin Brand System v3 monogram. Surrounding app-name text is an interface label. */
export function OpFinSymbol({ reverse = false }: { reverse?: boolean }) {
  return <Image
    src={reverse ? "/brand/opfin-symbol-reverse.svg" : "/brand/opfin-symbol.svg"}
    alt=""
    aria-hidden="true"
    width={38}
    height={42}
    className="opfin-symbol"
    unoptimized
  />;
}
