import React, { FC } from "react";

export interface FormInterface {
  content: FormContentInterface[];
}

export interface FormContentInterface {
  Container?: any;
  props?: any;
  Dom?: React.ReactNode;
  fields?: FieldInterface[];
  children?: FormContentInterface[];
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
};

export type FormInputOptions = {
  label: string;
  value: string;
  disabled?: boolean;
};
