// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import {
  FormInterface,
  InputInterface,
  TableLineInterface,
} from "../../../interface/InputInterface";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArtfournisseInterface } from "../../../interface/FArticleInterface";
import { MetadataInterface } from "../../../interface/WordpressInterface";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const [form] = React.useState<FormInterface>(() => {
    const prefix = "fArtfournisses";
    const fArtfournisses: FArtfournisseInterface[] = getListObjectSageMetadata(
      prefix,
      articleMeta,
      "ctNum",
    );

    const lines: TableLineInterface[][] = fArtfournisses.map(
      (fArtclient): TableLineInterface[] => {
        return [
          {
            Dom: <>{fArtclient.ctNum}</>,
          },
          {
            field: {
              name: prefix + "[" + fArtclient.ctNum + "].afPrincipal",
              DomField: FormInput,
              hideLabel: true,
            },
          },
          {
            field: {
              name: prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
              DomField: FormInput,
              hideLabel: true,
            },
          },
          {
            field: {
              name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
              DomField: FormInput,
              hideLabel: true,
            },
          },
        ];
      },
    );
    return {
      content: [
        {
          props: {
            container: true,
            spacing: 1,
            sx: { p: 1 },
          },
          children: [
            {
              props: {
                size: { xs: 12 },
              },
              table: {
                headers: [
                  translations.words.supplier,
                  translations.words.main,
                  translations.words.supplierRef,
                  translations.words.buyPrice,
                ],
                lines: lines,
              },
            },
          ],
        },
      ],
    };
  });

  const [flatFields] = React.useState(getFlatFields(form));
  const [fieldNames] = React.useState(flatFields.map((f) => f.name));

  type FieldKeys = (typeof fieldNames)[number];

  interface FormState extends Record<FieldKeys, InputInterface> {}

  const getDefaultValue = (): FormState => {
    const fieldValues = flatFields.reduce(
      (acc, field) => {
        acc[field.name] = {
          value: getSageMetadata(field.name, articleMeta) ?? "",
          validator: field.validator,
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      ...fieldValues,
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
