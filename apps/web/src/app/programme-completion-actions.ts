"use server";

import { redirect } from "next/navigation";
import { programmeCompletionApi } from "@/lib/api/programme-completion";
import { OpfinApiError } from "@/lib/api/errors";
import { getAccessToken } from "@/lib/auth/session";

function value(formData: FormData, key: string): string {
  return formData.get(key)?.toString().trim() ?? "";
}

function list(formData: FormData, key: string): string[] {
  return formData
    .getAll(key)
    .map((item) => item.toString())
    .filter(Boolean);
}

function errorRedirect(path: string, error: unknown): never {
  const kind = error instanceof OpfinApiError ? error.kind : "server";
  const message = error instanceof Error ? error.message : "Request failed";
  redirect(`${path}?error=${kind}&message=${encodeURIComponent(message)}`);
}

export async function submitProgrammeCheckInAction(formData: FormData) {
  const token = await getAccessToken();
  const instrumentId = Number(value(formData, "instrument_id"));
  const scheduleIdRaw = value(formData, "schedule_id");
  const locale = value(formData, "locale") || undefined;
  const questionIds = value(formData, "question_ids")
    .split(",")
    .map((item) => Number(item))
    .filter((id) => Number.isInteger(id) && id > 0);

  const answers: Array<{ question_id: number; value: unknown }> = [];
  for (const questionId of questionIds) {
    const type = value(formData, `question_type_${questionId}`);
    const key = `question_${questionId}`;
    if (type === "multi_choice") {
      const selected = list(formData, key);
      if (selected.length) answers.push({ question_id: questionId, value: selected });
      continue;
    }

    const raw = value(formData, key);
    if (!raw) continue;

    let parsed: unknown = raw;
    if (type === "boolean") parsed = raw === "true";
    if (type === "integer" || type === "currency_minor") {
      const number = Number(raw.replaceAll(",", ""));
      parsed = Number.isFinite(number) ? Math.trunc(number) : raw;
    }
    if (type === "decimal") {
      const number = Number(raw.replaceAll(",", ""));
      parsed = Number.isFinite(number) ? number : raw;
    }
    answers.push({ question_id: questionId, value: parsed });
  }

  try {
    await programmeCompletionApi.submitCheckIn(
      instrumentId,
      {
        schedule_id: scheduleIdRaw ? Number(scheduleIdRaw) : undefined,
        locale,
        answers
      },
      token
    );
  } catch (error) {
    errorRedirect("/programme/check-ins", error);
  }

  redirect("/programme/check-ins?status=saved");
}

export async function applyProgrammeTemplateAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));

  try {
    await programmeCompletionApi.applyTemplate(
      programmeId,
      value(formData, "template_code"),
      token
    );
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=template-applied`);
}

export async function generateProgrammeFollowUpsAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeIdRaw = value(formData, "programme_id");
  const programmeId = programmeIdRaw ? Number(programmeIdRaw) : undefined;

  try {
    await programmeCompletionApi.generateFollowUps(programmeId, token);
  } catch (error) {
    errorRedirect(
      programmeId ? `/admin/inclusion/delivery?programme_id=${programmeId}` : "/admin/inclusion/delivery",
      error
    );
  }

  redirect(
    programmeId
      ? `/admin/inclusion/delivery?programme_id=${programmeId}&status=followups-generated`
      : "/admin/inclusion/delivery?status=followups-generated"
  );
}

export async function inviteProgrammePartnerAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const partnerId = Number(value(formData, "partner_id"));

  try {
    await programmeCompletionApi.invitePartner(
      {
        programme_id: programmeId,
        partner_id: partnerId,
        invited_name: value(formData, "invited_name"),
        invited_phone: value(formData, "invited_phone") || undefined,
        invited_email: value(formData, "invited_email") || undefined,
        access_level: value(formData, "access_level") || "read_only"
      },
      token
    );

    redirect(
      `/admin/inclusion/delivery?programme_id=${programmeId}&status=partner-invited`
    );
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }
}

export async function createProgrammeInstrumentAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const channels = list(formData, "channels");
  const locales = list(formData, "supported_locales");
  const scheduleStages = list(formData, "schedule_stage");
  const scheduleOffsets = list(formData, "schedule_offset_days");
  const schedule = scheduleStages
    .map((stage, index) => ({
      stage,
      offset_days: Number(scheduleOffsets[index] ?? 0)
    }))
    .filter((item) => item.stage && Number.isInteger(item.offset_days) && item.offset_days >= 0);

  try {
    await programmeCompletionApi.createInstrument(
      {
        programme_id: programmeId,
        code: value(formData, "code"),
        name: value(formData, "name"),
        description: value(formData, "description") || undefined,
        outcome_domain: value(formData, "outcome_domain"),
        default_measurement_stage: value(formData, "default_measurement_stage") || "check_in",
        consent_classification: value(formData, "consent_classification") || "programme_measurement",
        channels: channels.length ? channels : ["app", "web"],
        default_locale: value(formData, "default_locale") || "en",
        supported_locales: locales.length ? locales : ["en"],
        schedule_config: schedule,
        status: value(formData, "status") || "draft",
        version: value(formData, "version") || "1.0"
      },
      token
    );
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=instrument-created`);
}

export async function addProgrammeQuestionAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const instrumentId = Number(value(formData, "instrument_id"));
  const options = value(formData, "options")
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);
  const minRaw = value(formData, "min");
  const maxRaw = value(formData, "max");
  const unit = value(formData, "unit");
  const validationRules: Record<string, unknown> = {};
  if (minRaw !== "" && Number.isFinite(Number(minRaw))) validationRules.min = Number(minRaw);
  if (maxRaw !== "" && Number.isFinite(Number(maxRaw))) validationRules.max = Number(maxRaw);
  if (unit) validationRules.unit = unit;

  try {
    await programmeCompletionApi.addQuestion(
      instrumentId,
      {
        code: value(formData, "question_code"),
        prompt: value(formData, "prompt"),
        help_text: value(formData, "help_text") || undefined,
        indicator_definition_id: value(formData, "indicator_definition_id")
          ? Number(value(formData, "indicator_definition_id"))
          : undefined,
        answer_type: value(formData, "answer_type"),
        required: formData.get("required") === "on",
        sort_order: Number(value(formData, "sort_order") || 0),
        options: options.length ? options : undefined,
        validation_rules: Object.keys(validationRules).length ? validationRules : undefined,
        verification_source: value(formData, "verification_source") || "self_reported"
      },
      token
    );
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=question-added`);
}

export async function addQuestionTranslationAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const questionId = Number(value(formData, "question_id"));
  const options = value(formData, "translated_options")
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);

  try {
    await programmeCompletionApi.upsertTranslation(
      questionId,
      {
        locale: value(formData, "locale"),
        prompt: value(formData, "translated_prompt"),
        help_text: value(formData, "translated_help_text") || undefined,
        options: options.length ? options : undefined
      },
      token
    );
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=translation-saved`);
}

export async function revokeProgrammePartnerAccessAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const userId = Number(value(formData, "user_id"));

  try {
    await programmeCompletionApi.revokePartnerAccess(programmeId, userId, token);
  } catch (error) {
    errorRedirect(`/admin/inclusion/delivery?programme_id=${programmeId}`, error);
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=partner-access-revoked`);
}

export async function submitAssistedProgrammeCheckInAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeId = Number(value(formData, "programme_id"));
  const instrumentId = Number(value(formData, "instrument_id"));
  const userId = Number(value(formData, "user_id"));
  const scheduleIdRaw = value(formData, "schedule_id");
  const locale = value(formData, "locale") || undefined;
  const questionIds = value(formData, "question_ids")
    .split(",")
    .map((item) => Number(item))
    .filter((id) => Number.isInteger(id) && id > 0);

  const answers: Array<{ question_id: number; value: unknown }> = [];
  for (const questionId of questionIds) {
    const type = value(formData, `question_type_${questionId}`);
    const key = `question_${questionId}`;
    if (type === "multi_choice") {
      const selected = list(formData, key);
      if (selected.length) answers.push({ question_id: questionId, value: selected });
      continue;
    }

    const raw = value(formData, key);
    if (!raw) continue;
    let parsed: unknown = raw;
    if (type === "boolean") parsed = raw === "true";
    if (type === "integer" || type === "currency_minor") {
      const number = Number(raw.replaceAll(",", ""));
      parsed = Number.isFinite(number) ? Math.trunc(number) : raw;
    }
    if (type === "decimal") {
      const number = Number(raw.replaceAll(",", ""));
      parsed = Number.isFinite(number) ? number : raw;
    }
    answers.push({ question_id: questionId, value: parsed });
  }

  try {
    await programmeCompletionApi.assistedCapture(
      instrumentId,
      {
        user_id: userId,
        schedule_id: scheduleIdRaw ? Number(scheduleIdRaw) : undefined,
        locale,
        answers
      },
      token
    );
  } catch (error) {
    errorRedirect(
      `/admin/inclusion/assisted?instrument_id=${instrumentId}&schedule_id=${scheduleIdRaw}&user_id=${userId}&programme_id=${programmeId}`,
      error
    );
  }

  redirect(`/admin/inclusion/delivery?programme_id=${programmeId}&status=assisted-checkin-saved`);
}

export async function configureProviderAdapterAction(formData: FormData) {
  const token = await getAccessToken();
  const programmeIdRaw = value(formData, "programme_id");
  const programmeId = programmeIdRaw ? Number(programmeIdRaw) : undefined;
  const allowedKeys = value(formData, "allowed_signal_keys")
    .split("\n")
    .map((item) => item.trim())
    .filter(Boolean);
  const mapping: Record<string, string> = {};
  for (const line of value(formData, "signal_mapping").split("\n")) {
    const [source, target] = line.split("=").map((item) => item.trim());
    if (source && target) mapping[source] = target;
  }

  try {
    await programmeCompletionApi.configureAdapter(
      {
        programme_id: programmeId,
        partner_id: value(formData, "partner_id") ? Number(value(formData, "partner_id")) : undefined,
        code: value(formData, "adapter_code"),
        name: value(formData, "adapter_name"),
        adapter_type: value(formData, "adapter_type"),
        status: value(formData, "adapter_status") || "draft",
        purpose: value(formData, "purpose") || "programme_measurement",
        allowed_signal_keys: allowedKeys,
        signal_mapping: mapping,
        requires_credit_processing_consent:
          formData.get("requires_credit_processing_consent") === "on",
        credentials_configured: formData.get("credentials_configured") === "on",
        legal_basis_confirmed: formData.get("legal_basis_confirmed") === "on",
        activation_notes: value(formData, "activation_notes") || undefined
      },
      token
    );
  } catch (error) {
    errorRedirect(
      programmeId ? `/admin/inclusion/delivery?programme_id=${programmeId}` : "/admin/inclusion/delivery",
      error
    );
  }

  redirect(
    programmeId
      ? `/admin/inclusion/delivery?programme_id=${programmeId}&status=adapter-saved`
      : "/admin/inclusion/delivery?status=adapter-saved"
  );
}

export async function recordCommercialCostAction(formData: FormData) {
  const token = await getAccessToken();
  try {
    await programmeCompletionApi.recordCommercialCost(
      {
        cost_type: value(formData, "cost_type"),
        channel: value(formData, "channel") || undefined,
        programme_id: value(formData, "programme_id")
          ? Number(value(formData, "programme_id"))
          : undefined,
        amount_minor: Number(value(formData, "amount_minor")),
        currency: "UGX",
        quantity: Number(value(formData, "quantity") || 1),
        source_reference: value(formData, "source_reference") || undefined,
        occurred_at: value(formData, "occurred_at") || undefined
      },
      token
    );
  } catch (error) {
    errorRedirect("/admin/commercial", error);
  }

  redirect("/admin/commercial?status=cost-recorded");
}

export async function recordAcquisitionAttributionAction(formData: FormData) {
  const token = await getAccessToken();
  const userId = Number(value(formData, "user_id"));
  try {
    await programmeCompletionApi.recordAttribution(
      userId,
      {
        acquisition_channel: value(formData, "acquisition_channel"),
        source: value(formData, "source") || undefined,
        campaign: value(formData, "campaign") || undefined,
        programme_id: value(formData, "programme_id")
          ? Number(value(formData, "programme_id"))
          : undefined,
        acquired_at: value(formData, "acquired_at") || undefined
      },
      token
    );
  } catch (error) {
    errorRedirect("/admin/commercial", error);
  }

  redirect("/admin/commercial?status=attribution-recorded");
}
