// Public build configuration only. FCM/APNs server credentials stay in EAS/server configuration.

// Store-bound builds must point at a public HTTPS API origin. The app rejects anything else
// at start-up, so fail the build here instead of shipping a binary that cannot sign in.
const releaseProfiles = new Set(["preview", "production"]);
function assertReleaseConfig(profile, projectId) {
  if (!releaseProfiles.has(profile)) return;
  const value = process.env.EXPO_PUBLIC_API_URL;
  let url;
  try {
    url = new URL(value);
  } catch {
    throw new Error(
      `EXPO_PUBLIC_API_URL must be set for the "${profile}" build (EAS environment "${profile}").`,
    );
  }
  if (
    url.protocol !== "https:" ||
    url.pathname !== "/" ||
    url.search ||
    url.hash
  )
    throw new Error(
      `EXPO_PUBLIC_API_URL must be an HTTPS origin without a path for the "${profile}" build.`,
    );
  if (!projectId)
    throw new Error(
      `An EAS project ID (app.json extra.eas.projectId) is required for the "${profile}" build.`,
    );
}

module.exports = ({ config }) => {
  const projectId = process.env.EXPO_PUBLIC_EAS_PROJECT_ID;
  assertReleaseConfig(
    process.env.EAS_BUILD_PROFILE,
    projectId || config.extra?.eas?.projectId,
  );
  const googleServicesFile = process.env.GOOGLE_SERVICES_JSON;
  return {
    ...config,
    android: {
      ...config.android,
      ...(googleServicesFile ? { googleServicesFile } : {}),
    },
    extra: {
      ...config.extra,
      ...(projectId ? { eas: { ...config.extra?.eas, projectId } } : {}),
    },
  };
};
