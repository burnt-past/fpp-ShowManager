#!/usr/bin/env python3
"""
Merge blackout (quiet-hours) entries into the Show Manager schedule.

Run this ON THE FPP BOX. It is non-destructive: it backs up the current
schedule, then adds/updates only the entries in the given JSON file, matched by
their "id" — so re-running is idempotent and your shows and rules are left
untouched. The scheduler reads the schedule live, so changes take effect within
a few seconds; no restart needed.

Usage:
    python3 scripts/import_blackouts.py presets/2026-mission-quiet-hours.json
    python3 scripts/import_blackouts.py <file.json> --config /path/to/Schedule.config
    python3 scripts/import_blackouts.py <file.json> --remove   # remove those ids instead

The JSON file is an array of entries, e.g.:
    [{"id":"qh_2026-11-14_1100","date":"2026-11-14","type":"blackout",
      "reason":"...","start_time":"11:00","end_time":"15:30"}]
"""
import argparse, datetime, json, os, sys

DEFAULT_CONFIG = "/home/fpp/media/config/ShowManagerSchedule.config"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("entries_file", help="JSON array of entries to merge")
    ap.add_argument("--config", default=DEFAULT_CONFIG, help="schedule config path")
    ap.add_argument("--remove", action="store_true",
                    help="remove the entries' ids instead of adding them")
    args = ap.parse_args()

    with open(args.entries_file) as f:
        new_entries = json.load(f)
    if not isinstance(new_entries, list):
        sys.exit("entries file must be a JSON array")

    if os.path.exists(args.config):
        with open(args.config) as f:
            sched = json.load(f)
        if not isinstance(sched, dict):
            sched = {"entries": [], "rules": []}
    else:
        sched = {"entries": [], "rules": []}
    sched.setdefault("entries", [])
    sched.setdefault("rules", [])

    # Back up first.
    if os.path.exists(args.config):
        stamp = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")
        bak = f"{args.config}.bak-{stamp}"
        with open(bak, "w") as f:
            json.dump(sched, f, indent=2)
        print(f"Backed up current schedule -> {bak}")

    by_id = {e.get("id"): e for e in sched["entries"] if e.get("id")}
    new_ids = {e["id"] for e in new_entries if e.get("id")}

    if args.remove:
        before = len(sched["entries"])
        sched["entries"] = [e for e in sched["entries"] if e.get("id") not in new_ids]
        print(f"Removed {before - len(sched['entries'])} entr(ies).")
    else:
        added = updated = 0
        for e in new_entries:
            eid = e.get("id")
            if not eid:
                print("  ! skipping an entry with no id"); continue
            if eid in by_id:
                updated += 1
            else:
                added += 1
            by_id[eid] = e
        # Preserve non-id entries, then take the merged id'd ones.
        sched["entries"] = [e for e in sched["entries"] if not e.get("id")] + list(by_id.values())
        print(f"Added {added}, updated {updated}; schedule now has "
              f"{len(sched['entries'])} entries.")

    with open(args.config, "w") as f:
        json.dump(sched, f, indent=2)
    print(f"Wrote {args.config}. Changes take effect within a few seconds "
          "(the scheduler reads the schedule live).")


if __name__ == "__main__":
    main()
