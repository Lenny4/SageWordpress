import React, { FC } from "react";

export interface FormInterface {
  content: FormContentInterface[];
  flatFields: FieldInterface[];
  fieldNames: string[];
}

export interface TableLineItemInterface {
  item: any;
  lines: TableLineInterface[];
}

export interface TableLineInterface {
  Dom?: React.ReactNode;
  field?: FieldInterface;
}

export interface TableInterface {
  headers: string[];
  items: TableLineItemInterface[];
  fullWidth?: boolean;
  add?: TableAddInterface;
  canDelete?: boolean;
  search?: Function;
  addItem?: Function;
  key?: string;
}

export interface TableAddInterface {
  table: TableInterface;
}

export interface FormContentInterface {
  Container?: any;
  props?: any;
  Dom?: React.ReactNode;
  fields?: FieldInterface[];
  children?: FormContentInterface[];
  table?: TableInterface;
}

export interface ErrorMessageInterface {
  fieldName: string;
  message: string;
}

export interface InputInterface<
  F extends (arg: any) => any = (arg: any) => any,
> {
  value: any;
  error?: string | null;
  readOnly?: boolean;
  validator?: FieldValidatorInterface<F>;
}

export interface FieldValidatorInterface<F extends (arg: any) => any> {
  functionName: F;
  params?: Parameters<F>[0]; // Extracts the shape of the single object parameter
}

export interface FieldInterface<
  F extends (arg: any) => any = (arg: any) => any,
> {
  name: string;
  DomField: FC<FormInputProps>;
  readOnly?: boolean;
  cannotBeChangeOnWebsite?: boolean;
  tooltip?: string;
  hideLabel?: boolean;
  options?: FormInputOptions[];
  type?: string;
  validator?: FieldValidatorInterface<F>;
  errorMessage?: string;
}

export type FormInputProps = {
  label: string;
  name: string;
  value: string;
  readOnly?: boolean;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onChangeSelect?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
  hideLabel?: boolean;
  options?: FormInputOptions[];
  type?: string;
  errorMessage?: string;
  cannotBeChangeOnWebsite?: boolean;
  tooltip?: string;
};

export type FormInputOptions = {
  label: string;
  value: string;
  disabled?: boolean;
};
