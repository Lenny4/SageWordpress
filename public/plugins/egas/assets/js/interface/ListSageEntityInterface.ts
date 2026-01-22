export interface FilterTypeInterface {
  DateTimeOperationFilterInput: string[];
  DecimalOperationFilterInput: string[];
  IntOperationFilterInput: string[];
  ShortOperationFilterInput: string[];
  StringOperationFilterInput: string[];
  UuidOperationFilterInput: string[];
}

export interface FilterShowFieldInterface {
  name: string;
  transDomain: string;
  isFilter: boolean;
  type: keyof FilterTypeInterface;
  values: string[] | any | null;
}
