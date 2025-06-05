// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FArtfournisseInterface } from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { AfPrincipalInput } from "../inputs/AfPrincipalInput";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import {
  numberValidator,
  stringValidator,
} from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtfournisses";
  const [fArtfournisses] = React.useState<FArtfournisseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "ctNum"),
  );
  const [selectedCtNum, setSelectedCtNum] = React.useState<string>(() => {
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  });
  const onAfPrincipalChanged: TriggerFormContentChanged = (name, newValue) => {
    setSelectedCtNum(newValue);
  };

  const getForm = (): FormInterface => {
    return {
      content: createFormContent({
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
              items: fArtfournisses.map(
                (fArtclient): TableLineItemInterface => {
                  return {
                    item: fArtclient,
                    lines: [
                      {
                        Dom: <>{fArtclient.ctNum}</>,
                      },
                      {
                        Dom: (
                          <AfPrincipalInput
                            selectedCtNum={selectedCtNum}
                            ctNum={fArtclient.ctNum}
                            onAfPrincipalChangedParent={onAfPrincipalChanged}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
                          DomField: FormInput,
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.ctNum +
                                "].afRefFourniss",
                              articleMeta,
                              fArtclient.afRefFourniss,
                            ),
                            validator: {
                              functionName: stringValidator,
                              params: {
                                maxLength: 19,
                                isReference: true,
                              },
                            },
                          },
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          type: "number",
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                              articleMeta,
                              fArtclient.afPrixAch,
                            ),
                            validator: {
                              functionName: numberValidator,
                              params: {
                                canBeEmpty: true,
                              },
                            },
                          },
                        },
                      },
                    ],
                  };
                },
              ),
            },
          },
        ],
      }),
    };
  };
  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [selectedCtNum]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
