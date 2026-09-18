import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

function source(relativeFromThisFile: string): string {
  return readFileSync(
    fileURLToPath(new URL(relativeFromThisFile, import.meta.url)),
    "utf8",
  );
}

describe("launch customer journey simplicity", () => {
  it("keeps primary mobile navigation focused", () => {
    const home = source("../../../client/lib/home_screen.dart");

    expect(home).toContain("label:'Home'");
    expect(home).toContain("label:'Borrow'");
    expect(home).toContain("label:'Activity'");
    expect(home).toContain("label:'More'");
    expect(home).not.toContain("label:'Save'");
    expect(home).not.toContain("label:'Grow'");
  });

  it("puts customer state before product catalogue complexity", () => {
    const home = source("../../../client/lib/home_screen.dart");

    expect(home).toContain("Amount due");
    expect(home).toContain("Available loan limit");
    expect(home).toContain("OpFin Score");
    expect(home).toContain("Add another phone (optional)");
  });

  it("uses one server-authoritative loan application journey", () => {
    const application = source("../../../client/lib/loan_application_screen.dart");
    const legacyAmount = source("../../../client/lib/loan_amount_screen.dart");
    const legacyDetails = source("../../../client/lib/loan_details_screen.dart");

    expect(application).toContain("/credit/profile");
    expect(application).toContain("/credit/options");
    expect(application).toContain("Available limit");
    expect(application).toContain("Amount due");
    expect(application).toContain("You will see every cost before accepting a loan");

    expect(legacyAmount).toContain("LoanApplicationScreen");
    expect(legacyDetails).toContain("LoanApplicationScreen");
    expect(legacyAmount).not.toContain("max: 50000");
    expect(legacyDetails).not.toContain("14 days");
    expect(legacyDetails).not.toContain("30 days");
  });

  it("keeps onboarding short and accessibility visible", () => {
    const onboarding = source("../../../client/lib/onboarding_screen.dart");
    const accessibility = source("../../../client/lib/accessibility_screen.dart");
    const kyc = source("../../../client/lib/kyc_setup_screen.dart");

    expect(onboarding).toContain("Skip");
    expect(onboarding).toContain("Know what matters");
    expect(accessibility).toContain("VoiceOver");
    expect(accessibility).toContain("TalkBack");
    expect(accessibility).toContain("Larger text");
    expect(kyc).toContain("I need help with this step");
    expect(kyc).toContain("Request assistance");
  });

  it("never asks for broad media or SMS permissions in Android manifest", () => {
    const manifest = source("../../../client/android/app/src/main/AndroidManifest.xml");

    expect(manifest).toContain("android.permission.CAMERA");
    expect(manifest).not.toContain("READ_SMS");
    expect(manifest).not.toContain("READ_MEDIA_IMAGES");
    expect(manifest).not.toContain("READ_EXTERNAL_STORAGE");
  });
});
