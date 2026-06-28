/** Dashboard mit Wunsch-Modal für eine offene Planungsrunde. */
export function grossanlassOpenRoundWishRoute(departmentId: string, roundId: string) {
  return {
    path: `/${departmentId}`,
    query: { wishRound: roundId },
  }
}
