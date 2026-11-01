#!/usr/bin/env python3
"""Explain the image gate without suppressing unfixed findings or scanner failures."""
import json
import os
from pathlib import Path
import sys


def cell(value):
    return str(value).replace("\r", " ").replace("\n", " ").replace("|", "\\|").replace("`", "'")


def summarize(directory, statuses):
    lines = ["## Backend image security gate", "",
             "High and critical findings block deployment, including findings without a vendor fix.", ""]
    blocked = False
    for component, status in statuses:
        lines += ["### " + cell(component), ""]
        try:
            report = json.loads((directory / (component + ".json")).read_text())
            if not isinstance(report.get("Results"), list):
                raise ValueError("Incomplete report")
            findings = [item for result in report["Results"] for item in result.get("Vulnerabilities", [])
                        if item["Severity"] in ("HIGH", "CRITICAL")]
        except (OSError, ValueError, KeyError, TypeError):
            lines += ["**Scan failed or produced an invalid report.** Check the scanner output above; "
                      "image access, network, vulnerability database or timeout errors can cause this.", ""]
            blocked = True
            continue
        if not findings:
            if status:
                lines += ["**Scanner exited unsuccessfully** despite no high/critical entries in its report. "
                          "Check the scanner output before retrying.", ""]
                blocked = True
            else:
                lines += ["Passed: no high/critical findings.", ""]
            continue
        blocked = True
        identifiers = {item["VulnerabilityID"] for item in findings}
        unfixed = sum(not item.get("FixedVersion") for item in findings)
        lines += [f"**Blocked: {len(findings)} package findings, {len(identifiers)} distinct advisories; "
                  f"{unfixed} findings have no vendor fix listed.**", "",
                  "| Severity | Advisory | Package | Installed | Fixed version |",
                  "| --- | --- | --- | --- | --- |"]
        ordered = sorted(findings, key=lambda item: (item["Severity"] != "CRITICAL", item["PkgName"],
                                                    item["VulnerabilityID"]))
        for item in ordered[:150]:
            lines.append("| " + " | ".join(cell(value) for value in (
                item["Severity"], item["VulnerabilityID"], item["PkgName"], item["InstalledVersion"],
                item.get("FixedVersion") or "No vendor fix listed")) + " |")
        if len(ordered) > 150:
            lines += ["", "Showing the first 150 entries; the JSON artifact contains every finding."]
        lines += [""]
    lines += ["Download the **image-security** workflow artifact for complete JSON reports. "
              "Updating the VPS operating system does not update packages inside these images. "
              "Rebuild patched images before retrying; rerunning the same unpatched image will fail again.", ""]
    return "\n".join(lines), blocked


def main():
    directory = Path(sys.argv[1])
    statuses = [(name, int(status)) for name, status in (value.split(":", 1) for value in sys.argv[2:])]
    if not statuses or any(name not in ("api", "database", "tools", "web", "edge") for name, _ in statuses):
        raise ValueError("Provide explicit image scan results.")
    summary, blocked = summarize(directory, statuses)
    print(summary)
    path = os.environ.get("GITHUB_STEP_SUMMARY")
    if path:
        with Path(path).open("a") as output:
            output.write(summary + "\n")
    sys.exit(1 if blocked else 0)


if __name__ == "__main__":
    main()
