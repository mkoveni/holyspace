import { useState, type ReactNode } from "react";
import { View } from "react-native";
import { Image } from "expo-image";
import { coverImageSource } from "@/core/http";
import { useSession } from "@/core/session";
import { colors } from "./theme";

/** The verified cover source for a member `coverImageUrl`, or null (no image, a foreign URL, or a failed load). */
function useCover(url: string | null | undefined) {
  const { origin } = useSession();
  const source = coverImageSource(url, origin);
  const [failed, setFailed] = useState<string | null>(null);
  return {
    source: source && failed !== source ? source : null,
    fail: () => setFailed(source),
  };
}

/** A wide rounded cover at the top of an event or community page. Renders nothing without an image. */
export function CoverBanner({
  url,
  label,
}: {
  url: string | null | undefined;
  label: string;
}) {
  const cover = useCover(url);
  if (!cover.source) return null;
  return (
    <View
      style={{
        borderRadius: 24,
        overflow: "hidden",
        backgroundColor: colors.sunken,
        borderWidth: 1,
        borderColor: colors.line,
      }}
    >
      <Image
        source={{ uri: cover.source }}
        accessibilityLabel={label}
        contentFit="cover"
        transition={200}
        cachePolicy="memory-disk"
        onError={cover.fail}
        style={{ width: "100%", aspectRatio: 16 / 9 }}
      />
    </View>
  );
}

/** A small square cover for list cards; shows `fallback` (today's date or icon tile) when there is no image. */
export function CoverThumb({
  url,
  size,
  fallback,
}: {
  url: string | null | undefined;
  size: number;
  fallback: ReactNode;
}) {
  const cover = useCover(url);
  if (!cover.source) return <>{fallback}</>;
  return (
    <Image
      source={{ uri: cover.source }}
      accessible={false}
      contentFit="cover"
      transition={150}
      cachePolicy="memory-disk"
      onError={cover.fail}
      style={{
        width: size,
        height: size,
        borderRadius: size * 0.32,
        backgroundColor: colors.sunken,
      }}
    />
  );
}
