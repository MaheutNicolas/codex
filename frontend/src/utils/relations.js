/** The key of a pair of entries, whichever side each one is on: aldric/mira and mira/aldric are the same pair. */
export const pairKey = (relation) =>
  relation.sourceId < relation.targetId ? `${relation.sourceId}|${relation.targetId}` : `${relation.targetId}|${relation.sourceId}`
