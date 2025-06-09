export interface ConditionFilterInterface {
  andFields?: ConditionFilterInterface;
  orFields?: ConditionFilterInterface;
  fields?: number[];
}
