import { HTMLInputTypeAttribute } from "react";
import { TabInterface } from "./TabInterface";
import { GridProps } from "@mui/material/Grid/Grid";
import { TabsProps } from "@mui/material/Tabs/Tabs";

export interface FormInterface {
  content: FormContentInterface;
}

export interface TableLineItemInterface {
  item: any;
  lines: TableLineInterface[];
}

export interface TableLineInterface {
  Dom?: any;
  field?: FieldInterface;
}

export interface TableInterface {
  headers: string[];
  items: TableLineItemInterface[];
  fullWidth?: boolean;
  add?: TableAddInterface;
  removeItem?: Function;
  search?: Function;
  addItem?: Function;
}

export interface TableAddInterface {
  table: TableInterface;
}

export interface FormTabInterface {
  tabProps?: TabsProps;
  tabs: TabInterface[];
}

export interface FormContentInterface {
  Container?: any;
  props?: GridProps;
  Dom?: any;
  fields?: FieldInterface[];
  children?: FormContentInterface[];
  table?: TableInterface;
  tabs?: FormTabInterface;
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
  label?: string;
  name: string;
  DomField: any;
  readOnly?: boolean;
  cannotBeChangeOnWebsite?: boolean;
  tooltip?: string;
  hideLabel?: boolean;
  triggerFormContentChanged?: TriggerFormContentChanged;
  options?: FormInputOptions[];
  type?: HTMLInputTypeAttribute | undefined;
  errorMessage?: string;
  initValues: InputInterface;
  ref?: any;
}

export interface TriggerFormContentChanged {
  (name: string, newValue: string): void;
}

export type FormInputOptions = {
  label: string;
  value: string;
  disabled?: boolean;
};
