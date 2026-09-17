import Image from "next/image";

/** The supplied monogram; surrounding app-name text is an interface label. */
export function OpFinSymbol({ reverse = false }: { reverse?: boolean }) {
  return <Image
    src={reverse ? "/brand/opfin-symbol-reverse.png" : "/brand/opfin-symbol.png"}
    alt=""
    aria-hidden="true"
    width={38}
    height={42}
    className="opfin-symbol"
    unoptimized
  />;
}
